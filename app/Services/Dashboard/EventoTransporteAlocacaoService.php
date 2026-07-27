<?php

namespace App\Services\Dashboard;

use App\Models\Enums\EventoCalendarioHistoricoAcao;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\EventoCalendarioTransporteEscopo;
use App\Models\Aluno;
use App\Models\EventoCalendario;
use App\Models\EventoCalendarioEscola;
use App\Models\EventoCalendarioTransporteAlocacao;
use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Turma;
use App\Models\User;
use App\Models\VeiculoTransporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EventoTransporteAlocacaoService
{
    public function __construct(
        private readonly EventoCalendarioWorkflowService $workflow,
    ) {}

    public function queryAtivas(User $ator, EventoCalendario $evento): Builder
    {
        $this->autorizarVisualizacao($ator, $evento);

        return EventoCalendarioTransporteAlocacao::query()
            ->ativas()
            ->where('evento_calendario_id', $evento->getKey())
            ->with([
                'veiculo:id,placa,identificacao,ativo',
                'motorista:id,nome,cpf,telefone,status',
                'motorista.servidorFuncoes' => function (HasMany $vinculos): void {
                    $vinculos
                        ->select(['id', 'servidor_id', 'funcao_administrativa_id', 'status'])
                        ->whereHas('funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes
                            ->motorista()
                            ->where('ativo', true));
                },
                'turmas:id,nome,id_serie,id_escola',
                'turmas.serie:id,nome',
                'turmas.escola:id,nome',
            ])
            ->orderBy('id');
    }

    public function adicionar(
        User $ator,
        EventoCalendario $evento,
        int $veiculoId,
        int $motoristaId,
        array $turmaIds = [],
        ?int $agendamentoId = null,
    ): EventoCalendarioTransporteAlocacao {
        return DB::transaction(function () use ($ator, $evento, $veiculoId, $motoristaId, $turmaIds, $agendamentoId): EventoCalendarioTransporteAlocacao {
            $evento = EventoCalendario::query()
                ->with([
                    'escolasAgendadas:id,evento_calendario_id,escola_id,precisa_transporte,escopo_transporte',
                    'escolasAgendadas.series:id',
                    'escolasAgendadas.turmas:id',
                ])
                ->lockForUpdate()
                ->findOrFail($evento->getKey());
            Gate::forUser($ator)->authorize('create', [EventoCalendarioTransporteAlocacao::class, $evento]);
            $this->validarEventoElegivel($evento);

            $veiculo = VeiculoTransporte::query()->lockForUpdate()->findOrFail($veiculoId);
            $motorista = Pessoa::query()->lockForUpdate()->findOrFail($motoristaId);
            $this->validarRecursosAtivos($veiculo, $motorista);
            $turmaIds = collect($turmaIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();
            $this->validarTurmas($evento, $turmaIds);
            $this->validarEscolaDaAlocacao($evento, $agendamentoId, $turmaIds);

            $alocacao = EventoCalendarioTransporteAlocacao::query()->create([
                'evento_calendario_id' => $evento->getKey(),
                'veiculo_transporte_id' => $veiculo->getKey(),
                'motorista_id' => $motorista->getKey(),
                'motorista_nome' => $motorista->nome,
                'motorista_cpf' => $motorista->cpf,
                'motorista_matricula' => $motorista->matricula,
                'criado_por_id' => $ator->getKey(),
            ]);
            $alocacao->turmas()->sync($turmaIds);

            $this->workflow->registrarTransporte(
                $evento,
                $ator,
                EventoCalendarioHistoricoAcao::ALOCACAO_TRANSPORTE_ADICIONADA,
                "Veículo {$veiculo->placa} vinculado ao motorista {$motorista->nome}.",
            );

            return $alocacao->load([
                'veiculo:id,placa,identificacao,ativo',
                'motorista:id,nome,cpf,telefone,status',
                'turmas:id,nome,id_serie,id_escola',
                'turmas.serie:id,nome',
                'turmas.escola:id,nome',
            ]);
        });
    }

    /** @param list<int|string> $turmaIds */
    public function atribuirTurmasDaEscola(
        User $ator,
        EventoCalendario $evento,
        int $agendamentoId,
        int $veiculoId,
        ?int $motoristaId,
        array $turmaIds,
    ): EventoCalendarioTransporteAlocacao {
        $existente = EventoCalendarioTransporteAlocacao::query()
            ->ativas()
            ->where('evento_calendario_id', $evento->getKey())
            ->where('veiculo_transporte_id', $veiculoId)
            ->first();

        if (! $existente) {
            if (! $motoristaId) {
                throw ValidationException::withMessages([
                    'motorista_id' => 'Selecione o motorista do veículo.',
                ]);
            }

            return $this->adicionar(
                $ator,
                $evento,
                $veiculoId,
                $motoristaId,
                $turmaIds,
                $agendamentoId,
            );
        }

        return DB::transaction(function () use ($ator, $evento, $agendamentoId, $existente, $motoristaId, $turmaIds): EventoCalendarioTransporteAlocacao {
            $evento = EventoCalendario::query()
                ->with([
                    'escolasAgendadas:id,evento_calendario_id,escola_id,precisa_transporte,escopo_transporte',
                    'escolasAgendadas.series:id',
                    'escolasAgendadas.turmas:id',
                ])
                ->lockForUpdate()
                ->findOrFail($evento->getKey());
            Gate::forUser($ator)->authorize('create', [EventoCalendarioTransporteAlocacao::class, $evento]);
            $this->validarEventoElegivel($evento);

            $alocacao = EventoCalendarioTransporteAlocacao::query()
                ->ativas()
                ->with(['turmas:id', 'motorista:id,nome,status'])
                ->lockForUpdate()
                ->findOrFail($existente->getKey());
            $veiculo = VeiculoTransporte::query()->lockForUpdate()->findOrFail($alocacao->veiculo_transporte_id);
            $motorista = $motoristaId
                ? Pessoa::query()->lockForUpdate()->findOrFail($motoristaId)
                : $alocacao->motorista;
            $this->validarRecursosAtivos($veiculo, $motorista);
            $turmaIds = collect($turmaIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();
            $this->validarTurmas($evento, $turmaIds);
            $this->validarEscolaDaAlocacao($evento, $agendamentoId, $turmaIds);

            $motoristaFoiSubstituido = (int) $alocacao->motorista_id !== (int) $motorista->getKey();
            if ($motoristaFoiSubstituido) {
                $alocacao->forceFill([
                    'motorista_id' => $motorista->getKey(),
                    'motorista_nome' => $motorista->nome,
                    'motorista_cpf' => $motorista->cpf,
                    'motorista_matricula' => $motorista->matricula,
                ])->save();
            }

            $alocacao->turmas()->syncWithoutDetaching($turmaIds);
            $this->workflow->registrarTransporte(
                $evento,
                $ator,
                EventoCalendarioHistoricoAcao::ALOCACAO_TRANSPORTE_ADICIONADA,
                $motoristaFoiSubstituido
                    ? "Motorista do veículo {$veiculo->placa} substituído por {$motorista->nome}."
                    : "Turmas adicionadas ao veículo {$veiculo->placa}.",
            );

            return $alocacao->load([
                'veiculo:id,placa,identificacao,ativo',
                'motorista:id,nome,cpf,telefone,status',
                'turmas:id,nome,id_serie,id_escola',
                'turmas.serie:id,nome',
                'turmas.escola:id,nome',
            ]);
        });
    }

    public function removerTurmasDaEscola(
        User $ator,
        EventoCalendarioTransporteAlocacao $alocacao,
        int $agendamentoId,
    ): void {
        DB::transaction(function () use ($ator, $alocacao, $agendamentoId): void {
            $alocacao = EventoCalendarioTransporteAlocacao::query()
                ->ativas()
                ->with([
                    'evento.escolasAgendadas:id,evento_calendario_id,escola_id,precisa_transporte',
                    'veiculo:id,placa',
                    'turmas:id,id_escola',
                ])
                ->lockForUpdate()
                ->findOrFail($alocacao->getKey());
            Gate::forUser($ator)->authorize('remove', $alocacao);

            $agendamento = $alocacao->evento->escolasAgendadas->firstWhere('id', $agendamentoId);
            abort_unless($agendamento, 404);
            $turmaIds = $alocacao->turmas
                ->where('id_escola', $agendamento->escola_id)
                ->pluck('id')->all();
            abort_if($turmaIds === [], 404);

            $alocacao->turmas()->detach($turmaIds);
            $restantes = $alocacao->turmas()->count();

            if ($restantes === 0) {
                $alocacao->forceFill([
                    'removido_por_id' => $ator->getKey(),
                    'removido_em' => now(),
                ])->save();
            }

            $this->workflow->registrarTransporte(
                $alocacao->evento,
                $ator,
                EventoCalendarioHistoricoAcao::ALOCACAO_TRANSPORTE_REMOVIDA,
                "Turmas da escola removidas do veículo {$alocacao->veiculo?->placa}.",
            );
        });
    }

    public function remover(User $ator, EventoCalendarioTransporteAlocacao $alocacao): EventoCalendarioTransporteAlocacao
    {
        return DB::transaction(function () use ($ator, $alocacao): EventoCalendarioTransporteAlocacao {
            $alocacao = EventoCalendarioTransporteAlocacao::query()
                ->with([
                    'evento.escolasAgendadas:id,evento_calendario_id,precisa_transporte',
                    'veiculo:id,placa',
                    'motorista:id,nome',
                ])
                ->lockForUpdate()
                ->findOrFail($alocacao->getKey());

            Gate::forUser($ator)->authorize('remove', $alocacao);

            if ($alocacao->estaAtiva()) {
                $alocacao->forceFill([
                    'removido_por_id' => $ator->getKey(),
                    'removido_em' => now(),
                ])->save();

                $this->workflow->registrarTransporte(
                    $alocacao->evento,
                    $ator,
                    EventoCalendarioHistoricoAcao::ALOCACAO_TRANSPORTE_REMOVIDA,
                    "Veículo {$alocacao->veiculo?->placa} desvinculado do motorista {$alocacao->motoristaNomeExibicao()}.",
                );
            }

            return $alocacao->fresh([
                'veiculo:id,placa,identificacao,ativo',
                'motorista:id,nome,cpf,telefone,status',
            ]);
        });
    }

    /** @return array{possui_recursos_inativos: bool, alocacoes: \Illuminate\Support\Collection<int, EventoCalendarioTransporteAlocacao>} */
    public function resumo(User $ator, EventoCalendario $evento): array
    {
        $alocacoes = $this->queryAtivas($ator, $evento)->get();
        $possuiRecursosInativos = $alocacoes->contains(
            fn (EventoCalendarioTransporteAlocacao $alocacao): bool => ! (bool) $alocacao->veiculo?->ativo
                || $alocacao->motorista?->status !== Pessoa::STATUS_ATIVO
                || ! ($alocacao->motorista?->servidorFuncoes?->contains(
                    'status',
                    ServidorFuncaoAdministrativa::STATUS_ATIVO,
                ) ?? false),
        );

        return [
            'possui_recursos_inativos' => $possuiRecursosInativos,
            'alocacoes' => $alocacoes,
        ];
    }

    /** @return array<int, string> */
    public function veiculoOptions(User $ator, EventoCalendario $evento): array
    {
        return $this->veiculosDisponiveis($ator, $evento)
            ->mapWithKeys(fn (VeiculoTransporte $veiculo): array => [
                $veiculo->getKey() => trim(sprintf(
                    '%s%s',
                    $veiculo->identificacao ? $veiculo->identificacao.' — ' : '',
                    $veiculo->placa,
                )),
            ])->all();
    }

    /** @return EloquentCollection<int, VeiculoTransporte> */
    public function veiculosDisponiveis(User $ator, EventoCalendario $evento): EloquentCollection
    {
        $this->autorizarGerenciamento($ator, $evento);
        return VeiculoTransporte::query()
            ->ativos()
            ->orderBy('identificacao')->orderBy('placa')
            ->get(['id', 'placa', 'identificacao']);
    }

    /** @return array<int, string> */
    public function motoristaOptions(User $ator, EventoCalendario $evento): array
    {
        $this->autorizarGerenciamento($ator, $evento);
        return $this->motoristasAtivos()
            ->orderBy('nome')->orderBy('id')
            ->pluck('nome', 'id')->all();
    }

    /** @return array<int, string> */
    public function turmaOptions(User $ator, EventoCalendario $evento): array
    {
        $this->autorizarGerenciamento($ator, $evento);
        $evento->loadMissing([
            'escolasAgendadas.series:id',
            'escolasAgendadas.turmas:id',
        ]);

        return $this->turmasElegiveisQuery($evento)
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->orderBy('id_escola')->orderBy('id_serie')->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Turma $turma): array => [
                $turma->getKey() => collect([
                    $turma->escola?->nome,
                    trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome),
                ])->filter()->implode(' — '),
            ])->all();
    }

    /** @return EloquentCollection<int, Turma> */
    public function turmasParticipantes(User $ator, EventoCalendario $evento): EloquentCollection
    {
        $this->autorizarVisualizacao($ator, $evento);
        $evento->loadMissing([
            'escolasAgendadas.series:id',
            'escolasAgendadas.turmas:id',
        ]);

        return $this->turmasElegiveisQuery($evento)
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->withCount(['alunos as estudantes_transporte_count' => fn (Builder $alunos): Builder => $alunos
                ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
                ->where('status', Aluno::STATUS_MATRICULADO)])
            ->orderBy('id_escola')->orderBy('id_serie')->orderBy('nome')
            ->get();
    }

    public function removerTodasDoEventoInternamente(EventoCalendario $evento, User $ator): int
    {
        Gate::forUser($ator)->authorize('update', $evento);

        $alocacoes = EventoCalendarioTransporteAlocacao::query()
            ->ativas()
            ->where('evento_calendario_id', $evento->getKey())
            ->lockForUpdate()
            ->get();

        if ($alocacoes->isEmpty()) {
            return 0;
        }

        $momento = now();
        EventoCalendarioTransporteAlocacao::query()
            ->whereKey($alocacoes->modelKeys())
            ->update([
                'removido_por_id' => $ator->getKey(),
                'removido_em' => $momento,
                'updated_at' => $momento,
            ]);

        $this->workflow->registrarTransporte(
            $evento,
            $ator,
            EventoCalendarioHistoricoAcao::ALOCACOES_TRANSPORTE_REMOVIDAS,
            'Alocações removidas porque o evento deixou de exigir transporte.',
        );

        return $alocacoes->count();
    }

    private function autorizarGerenciamento(User $ator, EventoCalendario $evento): void
    {
        Gate::forUser($ator)->authorize('viewAny', EventoCalendarioTransporteAlocacao::class);
        Gate::forUser($ator)->authorize('manageTransport', $evento);
    }

    private function autorizarVisualizacao(User $ator, EventoCalendario $evento): void
    {
        Gate::forUser($ator)->authorize('view', $evento);

        if (! $evento->possuiTransporte()) {
            throw ValidationException::withMessages([
                'evento' => 'Este evento não possui transporte configurado.',
            ]);
        }
    }

    private function validarEventoElegivel(EventoCalendario $evento): void
    {
        if (! $evento->possuiTransporte()) {
            throw ValidationException::withMessages([
                'evento' => 'Este evento não possui transporte configurado.',
            ]);
        }

        $statusPermiteAlocacao = in_array($evento->status, [
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            EventoCalendarioStatus::PUBLICADO,
        ], true) || (
            $evento->status === EventoCalendarioStatus::INATIVO
            && $evento->data_inicio->isFuture()
        );

        if (! $statusPermiteAlocacao) {
            throw ValidationException::withMessages([
                'evento' => 'Só é possível alocar transporte em eventos pendentes, publicados ou inativos com início futuro.',
            ]);
        }
    }

    private function validarRecursosAtivos(VeiculoTransporte $veiculo, Pessoa $motorista): void
    {
        if (! $veiculo->ativo) {
            throw ValidationException::withMessages([
                'veiculo' => 'O veículo informado está inativo.',
            ]);
        }

        if ($motorista->status !== Pessoa::STATUS_ATIVO || ! $this->motoristaAtivo($motorista)) {
            throw ValidationException::withMessages([
                'motorista' => 'O motorista informado não possui vínculo ativo de motorista.',
            ]);
        }
    }

    /** @param list<int> $turmaIds */
    private function validarTurmas(EventoCalendario $evento, array $turmaIds): void
    {
        if ($turmaIds === []) {
            throw ValidationException::withMessages([
                'turma_ids' => 'Selecione ao menos uma turma para o veículo.',
            ]);
        }

        Turma::query()
            ->whereKey($turmaIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        $validas = $this->turmasElegiveisQuery($evento)->whereKey($turmaIds)->count();
        if ($validas !== count($turmaIds)) {
            throw ValidationException::withMessages([
                'turma_ids' => 'Uma turma selecionada não pertence a este evento.',
            ]);
        }
    }

    /** @param list<int> $turmaIds */
    private function validarEscolaDaAlocacao(
        EventoCalendario $evento,
        ?int $agendamentoId,
        array $turmaIds,
    ): void {
        if ($agendamentoId === null) {
            return;
        }

        /** @var EventoCalendarioEscola|null $agendamento */
        $agendamento = $evento->escolasAgendadas->firstWhere('id', $agendamentoId);
        $escolas = Turma::query()->whereKey($turmaIds)->pluck('id_escola')->unique();

        if (! $agendamento || ! $agendamento->precisa_transporte || $escolas->count() !== 1
            || (int) $escolas->first() !== (int) $agendamento->escola_id) {
            throw ValidationException::withMessages([
                'turma_ids' => 'As turmas devem pertencer à mesma escola deste card.',
            ]);
        }
    }

    /** @param list<int> $turmaIds */
    private function turmasElegiveisQuery(EventoCalendario $evento): Builder
    {
        $agendamentos = $evento->escolasAgendadas->where('precisa_transporte', true);

        if ($agendamentos->isEmpty()) {
            return Turma::query()->whereRaw('1 = 0');
        }

        return Turma::query()->where(function (Builder $selecoes) use ($agendamentos): void {
            foreach ($agendamentos as $agendamento) {
                $selecoes->orWhere(function (Builder $turmas) use ($agendamento): void {
                    $turmas->where('id_escola', $agendamento->escola_id);

                    if ($agendamento->escopo_transporte === EventoCalendarioTransporteEscopo::SERIES) {
                        $turmas->whereIn('id_serie', $agendamento->series->modelKeys());
                    }

                    if ($agendamento->escopo_transporte === EventoCalendarioTransporteEscopo::TURMAS) {
                        $turmas->whereKey($agendamento->turmas->modelKeys());
                    }
                });
            }
        });
    }

    private function motoristaAtivo(Pessoa $motorista): bool
    {
        return $motorista->servidorFuncoes()
            ->ativos()
            ->whereHas('funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes
                ->motorista()->where('ativo', true))
            ->exists();
    }

    private function motoristasAtivos(): Builder
    {
        return Pessoa::query()
            ->where('status', Pessoa::STATUS_ATIVO)
            ->whereHas('servidorFuncoes', fn (Builder $vinculos): Builder => $vinculos
                ->ativos()
                ->whereHas('funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes
                    ->motorista()->where('ativo', true)));
    }
}

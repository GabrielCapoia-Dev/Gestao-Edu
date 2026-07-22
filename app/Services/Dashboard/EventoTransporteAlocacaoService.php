<?php

namespace App\Services\Dashboard;

use App\Models\Enums\EventoCalendarioHistoricoAcao;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\EventoCalendario;
use App\Models\EventoCalendarioTransporteAlocacao;
use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use App\Models\VeiculoTransporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EventoTransporteAlocacaoService
{
    public function __construct(
        private readonly EventoTransporteDisponibilidadeService $disponibilidade,
        private readonly EventoCalendarioWorkflowService $workflow,
    ) {}

    public function queryAtivas(User $ator, EventoCalendario $evento): Builder
    {
        $this->autorizarVisualizacao($ator, $evento);

        return $evento->alocacoesTransporteAtivas()
            ->with([
                'veiculo:id,placa,identificacao,capacidade_passageiros,ativo',
                'motorista:id,nome,cpf,telefone,status',
                'motorista.servidorFuncoes' => function (HasMany $vinculos): void {
                    $vinculos
                        ->select(['id', 'servidor_id', 'funcao_administrativa_id', 'status'])
                        ->whereHas('funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes
                            ->motorista()
                            ->where('ativo', true));
                },
            ])
            ->orderBy('id');
    }

    public function adicionar(
        User $ator,
        EventoCalendario $evento,
        int $veiculoId,
        int $motoristaId,
    ): EventoCalendarioTransporteAlocacao {
        return DB::transaction(function () use ($ator, $evento, $veiculoId, $motoristaId): EventoCalendarioTransporteAlocacao {
            $evento = EventoCalendario::query()
                ->with('escolasAgendadas:id,evento_calendario_id,precisa_transporte')
                ->lockForUpdate()
                ->findOrFail($evento->getKey());
            Gate::forUser($ator)->authorize('create', [EventoCalendarioTransporteAlocacao::class, $evento]);
            $this->validarEventoElegivel($evento);

            $veiculo = VeiculoTransporte::query()->lockForUpdate()->findOrFail($veiculoId);
            $motorista = Pessoa::query()->lockForUpdate()->findOrFail($motoristaId);
            $this->validarRecursosAtivos($veiculo, $motorista);

            $duplicada = EventoCalendarioTransporteAlocacao::query()
                ->ativas()
                ->where('evento_calendario_id', $evento->getKey())
                ->where(function (Builder $recursos) use ($veiculo, $motorista): void {
                    $recursos
                        ->where('veiculo_transporte_id', $veiculo->getKey())
                        ->orWhere('motorista_id', $motorista->getKey());
                })
                ->lockForUpdate()
                ->exists();

            if ($duplicada) {
                throw ValidationException::withMessages([
                    'alocacao' => 'O veículo ou motorista já está alocado neste evento.',
                ]);
            }

            $this->disponibilidade->validarDisponibilidade(
                $evento->data_inicio,
                $evento->data_fim,
                [$veiculo->getKey()],
                [$motorista->getKey()],
                $evento->getKey(),
                true,
            );

            $alocacao = EventoCalendarioTransporteAlocacao::query()->create([
                'evento_calendario_id' => $evento->getKey(),
                'veiculo_transporte_id' => $veiculo->getKey(),
                'motorista_id' => $motorista->getKey(),
                'criado_por_id' => $ator->getKey(),
            ]);

            $this->workflow->registrarTransporte(
                $evento,
                $ator,
                EventoCalendarioHistoricoAcao::ALOCACAO_TRANSPORTE_ADICIONADA,
                "Veículo {$veiculo->placa} vinculado ao motorista {$motorista->nome}.",
            );

            return $alocacao->load([
                'veiculo:id,placa,identificacao,capacidade_passageiros,ativo',
                'motorista:id,nome,cpf,telefone,status',
            ]);
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
                    "Veículo {$alocacao->veiculo?->placa} desvinculado do motorista {$alocacao->motorista?->nome}.",
                );
            }

            return $alocacao->fresh([
                'veiculo:id,placa,identificacao,capacidade_passageiros,ativo',
                'motorista:id,nome,cpf,telefone,status',
            ]);
        });
    }

    /** @return array{estudantes: int, capacidade: int, diferenca: int, capacidade_insuficiente: bool, possui_recursos_inativos: bool, alocacoes: \Illuminate\Support\Collection<int, EventoCalendarioTransporteAlocacao>} */
    public function resumo(User $ator, EventoCalendario $evento): array
    {
        $alocacoes = $this->queryAtivas($ator, $evento)->get();
        $estudantes = $evento->relationLoaded('escolasAgendadas')
            ? (int) $evento->escolasAgendadas
                ->where('precisa_transporte', true)
                ->sum('quantidade_estimada_transporte')
            : (int) $evento->escolasAgendadas()
                ->where('precisa_transporte', true)
                ->sum('quantidade_estimada_transporte');
        $capacidade = (int) $alocacoes->sum(
            fn (EventoCalendarioTransporteAlocacao $alocacao): int => $alocacao->veiculo?->ativo
                ? (int) $alocacao->veiculo->capacidade_passageiros
                : 0,
        );
        $diferenca = $capacidade - $estudantes;
        $possuiRecursosInativos = $alocacoes->contains(
            fn (EventoCalendarioTransporteAlocacao $alocacao): bool => ! (bool) $alocacao->veiculo?->ativo
                || $alocacao->motorista?->status !== Pessoa::STATUS_ATIVO
                || ! ($alocacao->motorista?->servidorFuncoes?->contains(
                    'status',
                    ServidorFuncaoAdministrativa::STATUS_ATIVO,
                ) ?? false),
        );

        return [
            'estudantes' => $estudantes,
            'capacidade' => $capacidade,
            'diferenca' => $diferenca,
            'capacidade_insuficiente' => $diferenca < 0,
            'possui_recursos_inativos' => $possuiRecursosInativos,
            'alocacoes' => $alocacoes,
        ];
    }

    /** @return array<int, string> */
    public function veiculoOptions(User $ator, EventoCalendario $evento): array
    {
        $this->autorizarGerenciamento($ator, $evento);
        $jaAlocados = $evento->alocacoesTransporteAtivas()
            ->pluck('veiculo_transporte_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $candidatos = VeiculoTransporte::query()
            ->ativos()
            ->when($jaAlocados !== [], fn (Builder $veiculos): Builder => $veiculos->whereNotIn('id', $jaAlocados));
        $ocupados = $this->disponibilidade->recursosIndisponiveis(
            $evento->data_inicio,
            $evento->data_fim,
            (clone $candidatos)->pluck('id')->all(),
            [],
            $evento->getKey(),
        );

        return $candidatos
            ->when($ocupados['veiculos'] !== [], fn (Builder $veiculos): Builder => $veiculos->whereNotIn('id', $ocupados['veiculos']))
            ->orderBy('identificacao')->orderBy('placa')
            ->get(['id', 'placa', 'identificacao', 'capacidade_passageiros'])
            ->mapWithKeys(fn (VeiculoTransporte $veiculo): array => [
                $veiculo->getKey() => trim(sprintf(
                    '%s%s (%d lugares)',
                    $veiculo->identificacao ? $veiculo->identificacao.' — ' : '',
                    $veiculo->placa,
                    $veiculo->capacidade_passageiros,
                )),
            ])->all();
    }

    /** @return array<int, string> */
    public function motoristaOptions(User $ator, EventoCalendario $evento): array
    {
        $this->autorizarGerenciamento($ator, $evento);
        $jaAlocados = $evento->alocacoesTransporteAtivas()
            ->pluck('motorista_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $motoristas = $this->motoristasAtivos()
            ->when($jaAlocados !== [], fn (Builder $pessoas): Builder => $pessoas->whereNotIn('id', $jaAlocados));
        $ocupados = $this->disponibilidade->recursosIndisponiveis(
            $evento->data_inicio,
            $evento->data_fim,
            [],
            $motoristas->pluck('id')->all(),
            $evento->getKey(),
        );

        return $motoristas
            ->when($ocupados['motoristas'] !== [], fn (Builder $pessoas): Builder => $pessoas->whereNotIn('id', $ocupados['motoristas']))
            ->orderBy('nome')->orderBy('id')
            ->pluck('nome', 'id')->all();
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

        if (! in_array($evento->status, [
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            EventoCalendarioStatus::PUBLICADO,
        ], true)) {
            throw ValidationException::withMessages([
                'evento' => 'Só é possível alocar transporte em eventos pendentes ou publicados.',
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

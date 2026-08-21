<?php

namespace App\Services;

use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class PessoaManutencaoService
{
    public function __construct(
        private readonly PessoaAcessoService $pessoaAcessoService,
        private readonly ProfessorMovimentacaoService $professorMovimentacaoService,
        private readonly ServidorService $servidorService,
        private readonly PessoaEquipeGestoraService $pessoaEquipeGestoraService,
    ) {}

    public function criarPessoaManutencao(array $dadosPessoa, array $dadosManutencao): Servidor
    {
        return DB::transaction(function () use ($dadosPessoa, $dadosManutencao): Servidor {
            $normalizado = $this->normalizarEValidar($dadosManutencao);
            $pessoa = Servidor::query()->create($this->dadosPessoa($dadosPessoa, statusPadraoAtivo: true));

            return $this->sincronizarInterno($pessoa, $normalizado);
        });
    }

    public function atualizarPessoaManutencao(
        Pessoa|Servidor $pessoa,
        array $dadosPessoa,
        array $dadosManutencao,
    ): Servidor {
        return DB::transaction(function () use ($pessoa, $dadosPessoa, $dadosManutencao): Servidor {
            $pessoa = Servidor::query()->lockForUpdate()->findOrFail($pessoa->id);

            if ($pessoa->professores()->where('ativo', true)->exists()) {
                throw ValidationException::withMessages([
                    $this->campoValidacao() => "Use a conversão de Professor para {$this->nomeCargo()} para preservar o histórico funcional.",
                ]);
            }

            if ($pessoa->vinculosAtivos()->whereHas('funcaoAdministrativa', fn ($query) => $query->equipeGestora())->exists()) {
                throw ValidationException::withMessages([
                    $this->campoValidacao() => "Use a conversão da Equipe Gestora para {$this->nomeCargo()} para preservar o histórico funcional.",
                ]);
            }

            $normalizado = $this->normalizarEValidar($dadosManutencao, $pessoa);
            $pessoa->update($this->dadosPessoa($dadosPessoa));

            return $this->sincronizarInterno($pessoa->fresh(), $normalizado);
        });
    }

    public function converterProfessorParaManutencao(
        Pessoa|Servidor $pessoa,
        array $dadosPessoa,
        array $dadosManutencao,
    ): Servidor {
        return DB::transaction(function () use ($pessoa, $dadosPessoa, $dadosManutencao): Servidor {
            $pessoa = Servidor::query()->lockForUpdate()->findOrFail($pessoa->id);
            $normalizado = $this->normalizarEValidar($dadosManutencao, $pessoa);
            $professores = $pessoa->professores()->where('ativo', true)->lockForUpdate()->get();

            $totalPendencias = $professores->sum(function (Professor $professor): int {
                $pendencias = $this->professorMovimentacaoService->pendenciasAvaliativas($professor);

                return (int) ($pendencias['preenchimentos_pendentes'] ?? 0);
            });

            if ($totalPendencias > 0) {
                throw ValidationException::withMessages([
                    $this->campoValidacao() => sprintf(
                        "A mudança para {$this->nomeCargo()} exige a conclusão das avaliações pendentes. Pendências: %d.",
                        $totalPendencias,
                    ),
                ]);
            }

            $pessoa->update($this->dadosPessoa($dadosPessoa));
            $this->servidorService->desvincularProfessorDePedagogico(
                $professores->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            );

            $vinculosProfessor = ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $pessoa->id)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                ->whereHas('funcaoAdministrativa', fn ($query) => $query->where('exige_professor', true))
                ->lockForUpdate()
                ->get();

            foreach ($vinculosProfessor as $vinculo) {
                $this->encerrarVinculo($vinculo);
            }

            Professor::query()
                ->whereKey($professores->pluck('id')->all())
                ->update([
                    'ativo' => false,
                    'desativado_em' => now(),
                    'desativado_por_id' => Auth::id(),
                    'motivo_desativacao' => "Conversão para {$this->nomeCargo()}",
                    'updated_at' => now(),
                ]);

            return $this->sincronizarInterno($pessoa->fresh(), $normalizado);
        });
    }

    public function converterEquipeGestoraParaManutencao(
        Pessoa|Servidor $pessoa,
        array $dadosPessoa,
        array $dadosManutencao,
    ): Servidor {
        return DB::transaction(function () use ($pessoa, $dadosPessoa, $dadosManutencao): Servidor {
            $pessoa = Servidor::query()->lockForUpdate()->findOrFail($pessoa->id);
            $normalizado = $this->normalizarEValidar($dadosManutencao, $pessoa);
            $pessoa->update($this->dadosPessoa($dadosPessoa));

            $this->pessoaEquipeGestoraService->encerrarVinculosEquipeGestora(
                $pessoa,
                reconciliarAcesso: false,
            );

            return $this->sincronizarInterno($pessoa->fresh(), $normalizado);
        });
    }

    public function encerrarVinculosManutencao(
        Pessoa|Servidor $pessoa,
        bool $reconciliarAcesso = true,
    ): void {
        DB::transaction(function () use ($pessoa, $reconciliarAcesso): void {
            $pessoa = Servidor::query()->lockForUpdate()->findOrFail($pessoa->id);
            $vinculos = $this->vinculosAtivos($pessoa);

            foreach ($vinculos as $vinculo) {
                $this->encerrarVinculo($vinculo);
            }

            $pessoa->forceFill([
                'id_escola' => null,
                'setor_id' => null,
            ])->save();

            if ($reconciliarAcesso) {
                $this->pessoaAcessoService->provisionarAcessosDoServidor($pessoa->fresh());
            }
        });
    }

    /** @param array<string, mixed> $normalizado */
    private function sincronizarInterno(Servidor $pessoa, array $normalizado): Servidor
    {
        PessoaMatricula::assertCompativelComCargaHoraria(
            $pessoa->carga_horaria,
            $pessoa->jornada,
            $normalizado['matriculas']->all(),
        );
        $matriculas = $this->sincronizarMatriculas($pessoa, $normalizado['matriculas']);
        $setor = Setor::query()->lockForUpdate()->findOrFail($normalizado['setor']->id);
        $funcao = $this->funcaoPadrao();

        if (! $funcao->rolesPadrao()->where('name', $this->nomeRole())->exists()) {
            throw new LogicException(
                "A role {$this->nomeRole()} ainda não foi vinculada ao cargo. Execute o comando permissoes:criar.",
            );
        }

        $outrosVinculos = ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->where('funcao_administrativa_id', '!=', $funcao->id)
            ->lockForUpdate()
            ->exists();

        if ($outrosVinculos) {
            throw ValidationException::withMessages([
                $this->campoValidacao() => "{$this->nomeCargo()} é um cargo exclusivo. Encerre o cargo funcional anterior antes de continuar.",
            ]);
        }

        $ativos = $this->vinculosAtivos($pessoa);
        $vinculo = $ativos->first(fn (ServidorFuncaoAdministrativa $item): bool => (int) $item->setor_id === (int) $setor->id);

        foreach ($ativos as $ativo) {
            if ($vinculo && (int) $ativo->id === (int) $vinculo->id) {
                continue;
            }

            $this->encerrarVinculo($ativo);
        }

        $vinculo ??= new ServidorFuncaoAdministrativa([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => $funcao->id,
            'origem' => $this->origemVinculo(),
            'data_inicio' => now()->toDateString(),
        ]);

        $vinculo->fill([
            'matricula' => $matriculas->first()?->matricula,
            'id_escola' => null,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'portaria' => null,
            'principal' => false,
            'data_inicio' => $vinculo->data_inicio ?? now()->toDateString(),
            'data_fim' => null,
        ])->save();

        $pessoa->forceFill([
            'id_escola' => null,
            'setor_id' => $setor->id,
            'matricula' => $matriculas->first()?->matricula,
        ])->save();

        $this->pessoaAcessoService->provisionarAcessosDoServidor($pessoa->fresh());

        return $this->carregar($pessoa->fresh());
    }

    /** @return array{setor: Setor, matriculas: Collection<int, array{id: int|null, matricula: string, turno: string}>} */
    private function normalizarEValidar(array $dados, ?Servidor $pessoa = null): array
    {
        $setor = Setor::query()
            ->whereKey((int) ($dados['setor_id'] ?? 0))
            ->where('ativo', true)
            ->where('exige_vinculo_escola', false)
            ->first();

        if (! $setor) {
            throw ValidationException::withMessages([
                'setor_id' => 'Selecione um setor ativo que não exija vínculo com escola.',
            ]);
        }

        $operador = Auth::user();
        if ($operador && ! app(PessoaScopeService::class)->canAccessSetor($operador, (int) $setor->id)) {
            throw ValidationException::withMessages([
                'setor_id' => 'O setor selecionado não pertence ao seu escopo de acesso.',
            ]);
        }

        $matriculas = collect($dados['matriculas'] ?? $dados['matriculas_professor'] ?? [])
            ->map(function (mixed $item): ?array {
                if (! is_array($item) || blank($item['matricula'] ?? null) || blank($item['turno'] ?? null)) {
                    return null;
                }

                return [
                    'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                    'matricula' => trim((string) $item['matricula']),
                    'turno' => (string) $item['turno'],
                ];
            })
            ->filter()
            ->values();

        if ($matriculas->isEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Informe ao menos uma matrícula da pessoa.',
            ]);
        }

        PessoaMatricula::assertConjuntoTurnosValido($matriculas->pluck('turno')->all());

        $duplicadas = $matriculas
            ->pluck('matricula')
            ->map(fn (string $matricula): string => mb_strtolower($matricula))
            ->duplicates();
        if ($duplicadas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Não é permitido repetir o número da matrícula.',
            ]);
        }

        $ids = $matriculas->pluck('id')->filter()->map(fn ($id): int => (int) $id);
        if ($ids->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Uma mesma matrícula não pode aparecer mais de uma vez.',
            ]);
        }

        if ($pessoa === null && $ids->isNotEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'A criação não aceita IDs de matrículas existentes.',
            ]);
        }

        if ($pessoa && $ids->isNotEmpty()) {
            $idsDaPessoa = PessoaMatricula::query()
                ->where('servidor_id', $pessoa->id)
                ->whereIn('id', $ids->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

            if ($ids->diff($idsDaPessoa)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'matriculas' => 'Uma matrícula informada não pertence a esta pessoa.',
                ]);
            }
        }

        return compact('setor', 'matriculas');
    }

    /** @param Collection<int, array{id: int|null, matricula: string, turno: string}> $dados */
    private function sincronizarMatriculas(Servidor $pessoa, Collection $dados): Collection
    {
        $mantidas = collect();

        foreach ($dados as $item) {
            $matricula = filled($item['id'])
                ? PessoaMatricula::query()->where('servidor_id', $pessoa->id)->find($item['id'])
                : null;
            $matricula ??= PessoaMatricula::query()
                ->where('servidor_id', $pessoa->id)
                ->where('matricula', $item['matricula'])
                ->first();
            $matricula ??= new PessoaMatricula(['servidor_id' => $pessoa->id]);

            $matricula->fill([
                'matricula' => $item['matricula'],
                'turno' => $item['turno'],
            ])->save();
            $mantidas->push($matricula->fresh());
        }

        $ausenteComHistorico = PessoaMatricula::query()
            ->where('servidor_id', $pessoa->id)
            ->whereNotIn('id', $mantidas->pluck('id')->all())
            ->whereHas('professores')
            ->first();
        if ($ausenteComHistorico) {
            throw ValidationException::withMessages([
                'matriculas' => "A matrícula {$ausenteComHistorico->matricula} possui histórico funcional e deve permanecer vinculada à pessoa.",
            ]);
        }

        PessoaMatricula::query()
            ->where('servidor_id', $pessoa->id)
            ->whereNotIn('id', $mantidas->pluck('id')->all())
            ->whereDoesntHave('professores')
            ->delete();

        return $mantidas;
    }

    private function encerrarVinculo(ServidorFuncaoAdministrativa $vinculo): void
    {
        $vinculo->forceFill([
            'principal' => false,
            'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
            'data_fim' => now()->toDateString(),
        ])->save();
    }

    /** @return Collection<int, ServidorFuncaoAdministrativa> */
    private function vinculosAtivos(Servidor $pessoa): Collection
    {
        return ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereHas('funcaoAdministrativa', fn (Builder $query) => $this->aplicarEscopoFuncao($query))
            ->lockForUpdate()
            ->get();
    }

    protected function nomeCargo(): string
    {
        return 'Manutenção';
    }

    protected function nomeRole(): string
    {
        return 'Manutenção';
    }

    protected function campoValidacao(): string
    {
        return 'manutencao';
    }

    protected function origemVinculo(): string
    {
        return 'manutencao';
    }

    protected function funcaoPadrao(): FuncaoAdministrativa
    {
        return FuncaoAdministrativa::manutencaoPadrao();
    }

    protected function aplicarEscopoFuncao(Builder $query): Builder
    {
        return $query->manutencao();
    }

    private function carregar(Servidor $pessoa): Servidor
    {
        return $pessoa->fresh([
            'matriculas',
            'professores',
            'user',
            'vinculosAtivos.funcaoAdministrativa.rolesPadrao',
            'vinculosAtivos.setor',
        ]);
    }

    /** @return array<string, mixed> */
    private function dadosPessoa(array $dados, bool $statusPadraoAtivo = false): array
    {
        $normalizados = collect($dados)
            ->only([
                'cpf',
                'user_id',
                'nome',
                'email',
                'telefone',
                'status',
                'observacoes',
                'carga_horaria',
                'jornada',
                'lotacao_id',
            ])
            ->all();

        if ($statusPadraoAtivo && ! array_key_exists('status', $normalizados)) {
            $normalizados['status'] = Pessoa::STATUS_ATIVO;
        }

        return $normalizados;
    }
}

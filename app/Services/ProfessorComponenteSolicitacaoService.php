<?php

namespace App\Services;

use App\Models\Professor;
use App\Models\ProfessorComponenteSolicitacao;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfessorComponenteSolicitacaoService
{
    public function __construct(private readonly PessoaScopeService $scope) {}

    /** @return SupportCollection<int, array<string, mixed>> */
    public function contextosDoProfessor(User $user): SupportCollection
    {
        $professores = $user->professores()
            ->where('ativo', true)
            ->whereNotNull('id_escola')
            ->with(['escola', 'professorMatricula'])
            ->get();

        if ($professores->isEmpty()) {
            return collect();
        }

        $turmas = Turma::query()
            ->whereIn('id_escola', $professores->pluck('id_escola')->unique())
            ->with(['serie.componentesCurriculares', 'escola'])
            ->orderBy('id_escola')->orderBy('id_serie')->orderBy('nome')->get();
        $vinculos = TurmaComponenteProfessor::query()
            ->whereIn('turma_id', $turmas->pluck('id'))
            ->with(['componente', 'professor.pessoa'])
            ->get();
        $vinculosPorTurma = $vinculos->groupBy('turma_id');
        $pendentes = ProfessorComponenteSolicitacao::query()
            ->whereIn('professor_id', $professores->pluck('id'))
            ->whereIn('turma_componente_professor_id', $vinculos->pluck('id'))
            ->where('status', ProfessorComponenteSolicitacao::STATUS_PENDENTE)
            ->get()
            ->groupBy(fn (ProfessorComponenteSolicitacao $item): string => $item->professor_id.':'.$item->turma_componente_professor_id);

        return $professores
            ->groupBy(fn (Professor $professor): string => $professor->professor_matricula_id
                ? 'matricula:'.$professor->professor_matricula_id
                : 'legado:'.($professor->matricula ?: $professor->id))
            ->map(function (SupportCollection $porMatricula, string $chaveMatricula) use ($turmas, $vinculosPorTurma, $pendentes): array {
                $primeiro = $porMatricula->first();

                return [
                    'chave' => $chaveMatricula,
                    'matricula' => $primeiro->professorMatricula?->matricula ?: ($primeiro->matricula ?: 'Não informada'),
                    'escolas' => $porMatricula->groupBy('id_escola')->map(function (SupportCollection $porEscola, int|string $escolaId) use ($turmas, $vinculosPorTurma, $pendentes): array {
                        $professor = $porEscola->first();
                        $professorIds = $porEscola->pluck('id');
                        $turmasDaEscola = $turmas->where('id_escola', (int) $escolaId);
                        $opcoes = collect();
                        $atuais = collect();
                        $turmasComComponentes = collect();

                        foreach ($turmasDaEscola as $turma) {
                            $vinculosDaTurma = $vinculosPorTurma->get($turma->id, collect());
                            $componentes = $turma->serie?->componentesCurriculares ?? collect();
                            $componentes = $componentes->merge($vinculosDaTurma->pluck('componente')->filter())->unique('id')->sortBy('nome');
                            $componentesDaTurma = collect();

                            foreach ($componentes as $componente) {
                                $vinculo = $vinculosDaTurma->firstWhere('componente_curricular_id', $componente->id);
                                $meu = $vinculo && $professorIds->contains($vinculo->professor_id) && $vinculo->tem_professor;
                                $ocupado = $vinculo && $vinculo->professor_id !== null && $vinculo->tem_professor;
                                $opcao = [
                                    'turma' => $turma,
                                    'componente' => $componente,
                                    'estado' => $meu ? 'meu' : ($ocupado ? 'ocupado' : 'vago'),
                                    'professor_atual' => $ocupado ? $vinculo->professor?->nomeCanonico() : null,
                                    'pendente' => $vinculo && $professorIds->contains(
                                        fn (int $id): bool => $pendentes->has($id.':'.$vinculo->id),
                                    ),
                                ];
                                $componentesDaTurma->push($opcao);

                                if ($meu) {
                                    $atuais->push($opcao);
                                } else {
                                    $opcoes->push($opcao);
                                }
                            }

                            $turmasComComponentes->push(['turma' => $turma, 'componentes' => $componentesDaTurma]);
                        }

                        return [
                            'id' => (int) $escolaId,
                            'nome' => $professor->escola?->nome ?: 'Escola não informada',
                            'professor_id' => $professor->id,
                            'atuais' => $atuais,
                            'opcoes' => $opcoes,
                            'series' => $turmasComComponentes
                                ->groupBy(fn (array $item): int => (int) $item['turma']->id_serie)
                                ->map(fn (SupportCollection $itens): array => [
                                    'nome' => $itens->first()['turma']->serie?->nome ?: 'Série não informada',
                                    'turmas' => $itens->values(),
                                ])->values(),
                        ];
                    })->values(),
                ];
            })->values();
    }

    public function solicitarComponente(User $user, int $professorId, int $turmaId, int $componenteId): ProfessorComponenteSolicitacao
    {
        return DB::transaction(function () use ($user, $professorId, $turmaId, $componenteId): ProfessorComponenteSolicitacao {
            $professor = $this->professoresAtivos($user)->firstWhere('id', $professorId);

            if (! $professor) {
                throw new AuthorizationException('Você não possui este vínculo de professor ativo.');
            }

            // Serializa também a criação do vínculo que ainda não existe.
            $turma = Turma::query()->lockForUpdate()->findOrFail($turmaId);
            if ((int) $turma->id_escola !== (int) $professor->id_escola) {
                throw new AuthorizationException('Esta turma não pertence à escola do seu vínculo.');
            }

            $componenteValido = $turma->serie?->componentesCurriculares()->whereKey($componenteId)->exists()
                || TurmaComponenteProfessor::query()->where('turma_id', $turmaId)->where('componente_curricular_id', $componenteId)->exists();
            if (! $componenteValido) {
                throw ValidationException::withMessages(['componente' => 'Este componente não pertence à turma.']);
            }

            $vinculo = TurmaComponenteProfessor::query()->firstOrCreate(
                ['turma_id' => $turmaId, 'componente_curricular_id' => $componenteId],
                ['professor_id' => null, 'tem_professor' => false],
            );

            if ((int) $vinculo->professor_id === $professor->id && $vinculo->tem_professor) {
                throw ValidationException::withMessages(['componente' => 'Você já está vinculado a este componente.']);
            }

            return ProfessorComponenteSolicitacao::query()->updateOrCreate(
                ['turma_componente_professor_id' => $vinculo->id, 'professor_id' => $professor->id],
                ['solicitado_por_id' => $user->id, 'status' => ProfessorComponenteSolicitacao::STATUS_PENDENTE,
                    'analisado_por_id' => null, 'analisado_em' => null, 'motivo_rejeicao' => null],
            );
        });
    }

    /** @return Collection<int, TurmaComponenteProfessor> */
    public function vinculosAtuais(User $user): Collection
    {
        $professorIds = $this->professoresAtivos($user)->pluck('id');

        if ($professorIds->isEmpty()) {
            return new Collection;
        }

        return TurmaComponenteProfessor::query()
            ->whereIn('professor_id', $professorIds)
            ->where('tem_professor', true)
            ->with(['turma.escola', 'turma.serie', 'componente'])
            ->orderBy('turma_id')
            ->orderBy('componente_curricular_id')
            ->get();
    }

    /** @return Collection<int, TurmaComponenteProfessor> */
    public function opcoesDisponiveis(User $user): Collection
    {
        $professores = $this->professoresAtivos($user);

        if ($professores->isEmpty()) {
            return new Collection;
        }

        $professorIds = $professores->pluck('id');

        return TurmaComponenteProfessor::query()
            ->where(fn ($vinculos) => $vinculos->whereNull('professor_id')->orWhereNotIn('professor_id', $professorIds))
            ->whereHas('turma', fn ($turmas) => $turmas->whereIn('id_escola', $professores->pluck('id_escola')->unique()))
            ->with([
                'turma.escola',
                'turma.serie',
                'componente',
                'solicitacoes' => fn ($solicitacoes) => $solicitacoes
                    ->whereIn('professor_id', $professorIds)
                    ->where('status', ProfessorComponenteSolicitacao::STATUS_PENDENTE),
            ])
            ->orderBy('turma_id')
            ->orderBy('componente_curricular_id')
            ->get();
    }

    /** @return Collection<int, ProfessorComponenteSolicitacao> */
    public function solicitacoesDoProfessor(User $user): Collection
    {
        $professorIds = $this->professoresAtivos($user)->pluck('id');

        if ($professorIds->isEmpty()) {
            return new Collection;
        }

        return ProfessorComponenteSolicitacao::query()
            ->whereIn('professor_id', $professorIds)
            ->with(['vinculo.turma.escola', 'vinculo.turma.serie', 'vinculo.componente'])
            ->latest()
            ->get();
    }

    /** @return Collection<int, ProfessorComponenteSolicitacao> */
    public function solicitacoesParaAnalise(User $user): Collection
    {
        if (! $this->podeAnalisar($user)) {
            return new Collection;
        }

        $query = ProfessorComponenteSolicitacao::query()
            ->where('status', ProfessorComponenteSolicitacao::STATUS_PENDENTE)
            ->with(['professor.pessoa', 'vinculo.turma.escola', 'vinculo.turma.serie', 'vinculo.componente', 'vinculo.professor.pessoa']);

        if (! $user->hasRole('Admin')) {
            $escolaIds = $this->scope->escolaIdsDosVinculos($user);
            $query->whereHas('vinculo.turma', fn ($turmas) => $turmas->whereIn('id_escola', $escolaIds));
        }

        return $query->oldest()->get();
    }

    /** @return Collection<int, ProfessorComponenteSolicitacao> */
    public function solicitacoesParaAnaliseDoServidor(User $user, int $servidorId): Collection
    {
        return $this->solicitacoesParaAnalise($user)
            ->filter(fn (ProfessorComponenteSolicitacao $solicitacao): bool => (int) $solicitacao->professor?->servidor_id === $servidorId)
            ->values();
    }

    public function solicitar(User $user, int $vinculoId): ProfessorComponenteSolicitacao
    {
        return DB::transaction(function () use ($user, $vinculoId): ProfessorComponenteSolicitacao {
            $vinculo = TurmaComponenteProfessor::query()
                ->with('turma:id,id_escola')
                ->lockForUpdate()
                ->findOrFail($vinculoId);
            $professor = $this->professoresAtivos($user)
                ->firstWhere('id_escola', (int) $vinculo->turma->id_escola);

            if (! $professor) {
                throw new AuthorizationException('Você não possui vínculo de professor ativo nesta escola.');
            }

            if ($professor->id === (int) $vinculo->professor_id && $vinculo->tem_professor) {
                throw ValidationException::withMessages([
                    'vinculo' => 'Você já está vinculado a este componente.',
                ]);
            }

            return ProfessorComponenteSolicitacao::query()->updateOrCreate(
                [
                    'turma_componente_professor_id' => $vinculo->id,
                    'professor_id' => $professor->id,
                ],
                [
                    'solicitado_por_id' => $user->id,
                    'status' => ProfessorComponenteSolicitacao::STATUS_PENDENTE,
                    'analisado_por_id' => null,
                    'analisado_em' => null,
                    'motivo_rejeicao' => null,
                ],
            );
        });
    }

    public function aprovar(User $user, int $solicitacaoId): ProfessorComponenteSolicitacao
    {
        return DB::transaction(function () use ($user, $solicitacaoId): ProfessorComponenteSolicitacao {
            $solicitacao = ProfessorComponenteSolicitacao::query()
                ->with(['vinculo.turma:id,id_escola', 'professor:id,id_escola,ativo'])
                ->lockForUpdate()
                ->findOrFail($solicitacaoId);

            $this->autorizarAnalise($user, (int) $solicitacao->vinculo->turma->id_escola);

            if ($solicitacao->status !== ProfessorComponenteSolicitacao::STATUS_PENDENTE) {
                throw ValidationException::withMessages(['solicitacao' => 'Esta solicitação já foi analisada.']);
            }

            $vinculo = TurmaComponenteProfessor::query()->lockForUpdate()->findOrFail($solicitacao->turma_componente_professor_id);

            if (! $solicitacao->professor->ativo || (int) $solicitacao->professor->id_escola !== (int) $solicitacao->vinculo->turma->id_escola) {
                throw ValidationException::withMessages(['solicitacao' => 'O vínculo funcional do professor não está mais válido para esta escola.']);
            }

            $professorAnteriorId = $vinculo->professor_id;

            $vinculo->update([
                'professor_id' => $solicitacao->professor_id,
                'tem_professor' => true,
            ]);

            app(ProfessorEscolaVinculoService::class)->sincronizarPorProfessores(
                array_values(array_filter([$professorAnteriorId, $solicitacao->professor_id])),
            );

            $solicitacao->update([
                'status' => ProfessorComponenteSolicitacao::STATUS_APROVADA,
                'analisado_por_id' => $user->id,
                'analisado_em' => now(),
                'motivo_rejeicao' => null,
            ]);

            ProfessorComponenteSolicitacao::query()
                ->where('turma_componente_professor_id', $vinculo->id)
                ->whereKeyNot($solicitacao->id)
                ->where('status', ProfessorComponenteSolicitacao::STATUS_PENDENTE)
                ->update([
                    'status' => ProfessorComponenteSolicitacao::STATUS_REJEITADA,
                    'analisado_por_id' => $user->id,
                    'analisado_em' => now(),
                    'motivo_rejeicao' => 'Componente atribuído a outro professor.',
                ]);

            return $solicitacao->refresh();
        });
    }

    public function rejeitar(User $user, int $solicitacaoId): ProfessorComponenteSolicitacao
    {
        return DB::transaction(function () use ($user, $solicitacaoId): ProfessorComponenteSolicitacao {
            $solicitacao = ProfessorComponenteSolicitacao::query()
                ->with('vinculo.turma:id,id_escola')
                ->lockForUpdate()
                ->findOrFail($solicitacaoId);

            $this->autorizarAnalise($user, (int) $solicitacao->vinculo->turma->id_escola);

            if ($solicitacao->status !== ProfessorComponenteSolicitacao::STATUS_PENDENTE) {
                throw ValidationException::withMessages(['solicitacao' => 'Esta solicitação já foi analisada.']);
            }

            $solicitacao->update([
                'status' => ProfessorComponenteSolicitacao::STATUS_REJEITADA,
                'analisado_por_id' => $user->id,
                'analisado_em' => now(),
                'motivo_rejeicao' => 'Solicitação recusada pela equipe responsável.',
            ]);

            return $solicitacao->refresh();
        });
    }

    public function podeAnalisar(User $user): bool
    {
        return $user->hasRole('Admin') || $this->scope->ehEquipeGestora($user);
    }

    /** @return Collection<int, Professor> */
    private function professoresAtivos(User $user): Collection
    {
        return $user->professores()
            ->where('ativo', true)
            ->whereNotNull('id_escola')
            ->get(['id', 'user_id', 'servidor_id', 'id_escola', 'ativo']);
    }

    private function autorizarAnalise(User $user, int $escolaId): void
    {
        if ($user->hasRole('Admin')) {
            return;
        }

        if (! $this->scope->ehEquipeGestora($user)
            || ! in_array($escolaId, $this->scope->escolaIdsDosVinculos($user), true)) {
            throw new AuthorizationException('Você não pode analisar solicitações desta escola.');
        }
    }
}

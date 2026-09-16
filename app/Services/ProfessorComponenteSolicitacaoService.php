<?php

namespace App\Services;

use App\Models\Professor;
use App\Models\ProfessorComponenteSolicitacao;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfessorComponenteSolicitacaoService
{
    public function __construct(private readonly PessoaScopeService $scope) {}

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
            ->whereNull('professor_id')
            ->where('tem_professor', false)
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
            ->with(['professor.pessoa', 'vinculo.turma.escola', 'vinculo.turma.serie', 'vinculo.componente']);

        if (! $user->hasRole('Admin')) {
            $escolaIds = $this->scope->escolaIdsDosVinculos($user);
            $query->whereHas('vinculo.turma', fn ($turmas) => $turmas->whereIn('id_escola', $escolaIds));
        }

        return $query->oldest()->get();
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

            if ($vinculo->professor_id !== null || $vinculo->tem_professor) {
                throw ValidationException::withMessages([
                    'vinculo' => 'Este componente já possui professor.',
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

            if ($vinculo->professor_id !== null || $vinculo->tem_professor) {
                throw ValidationException::withMessages(['solicitacao' => 'Este componente já possui professor.']);
            }

            $vinculo->update([
                'professor_id' => $solicitacao->professor_id,
                'tem_professor' => true,
            ]);

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

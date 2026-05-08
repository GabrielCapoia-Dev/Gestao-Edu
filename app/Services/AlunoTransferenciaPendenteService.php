<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Notifications\SistemaNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AlunoTransferenciaPendenteService
{
    public function pendenciaAtivaParaProfessor(?User $user): ?Aluno
    {
        if (! $user?->ehProfessor()) {
            return null;
        }

        $pendentes = Aluno::query()
            ->with(['pendenciaOrigem.turma.escola', 'pendenciaOrigem.turma.serie'])
            ->where('status', Aluno::STATUS_PENDENTE)
            ->whereNotNull('pendencia_origem_aluno_id')
            ->orderBy('status_alterado_em')
            ->orderBy('id')
            ->get();

        foreach ($pendentes as $pendente) {
            $origem = $pendente->pendenciaOrigem;

            if (! $origem?->estaMatriculado()) {
                continue;
            }

            if ($this->professorVinculadoAoAluno($user, $origem)) {
                return $origem;
            }
        }

        return null;
    }

    public function professorEstaBloqueado(?User $user): bool
    {
        return $this->pendenciaAtivaParaProfessor($user) !== null;
    }

    public function professorEstaRestritoAoAluno(?User $user, Aluno $aluno): bool
    {
        $pendencia = $this->pendenciaAtivaParaProfessor($user);

        return $pendencia !== null && (int) $pendencia->id === (int) $aluno->id;
    }

    public function professorPodeResponderComponente(?User $user, Aluno $aluno, ?int $componenteId): bool
    {
        if (! $this->professorEstaRestritoAoAluno($user, $aluno)) {
            return true;
        }

        if (! $componenteId) {
            return false;
        }

        return in_array((int) $componenteId, $this->componentesPermitidosParaProfessor($user, $aluno), true);
    }

    public function componentesPermitidosParaProfessor(?User $user, Aluno $aluno): array
    {
        if (! $user) {
            return [];
        }

        $professorIds = $this->professorIdsDoUsuario($user);

        if ($professorIds === [] || ! $aluno->id_turma) {
            return [];
        }

        $componentesComAvaliacao = $this->componentesComAvaliacaoDaTurma($aluno);

        if ($componentesComAvaliacao === []) {
            return [];
        }

        return TurmaComponenteProfessor::query()
            ->where('turma_id', (int) $aluno->id_turma)
            ->whereIn('professor_id', $professorIds)
            ->whereNotNull('componente_curricular_id')
            ->whereIn('componente_curricular_id', $componentesComAvaliacao)
            ->pluck('componente_curricular_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function professorIdParaComponente(?User $user, Aluno $aluno, ?int $componenteId): ?int
    {
        if (! $user || ! $componenteId) {
            return null;
        }

        $professorIds = $this->professorIdsDoUsuario($user);

        if ($professorIds === []) {
            return null;
        }

        $professorId = TurmaComponenteProfessor::query()
            ->where('turma_id', (int) $aluno->id_turma)
            ->where('componente_curricular_id', (int) $componenteId)
            ->whereIn('professor_id', $professorIds)
            ->value('professor_id');

        return $professorId ? (int) $professorId : null;
    }

    public function notificarPendencia(Aluno $pendente, ?User $solicitante = null, bool $dedupeHoje = false): int
    {
        $pendente->loadMissing(['turma.escola', 'pendenciaOrigem.turma.escola']);
        $origem = $pendente->pendenciaOrigem;

        if (! $origem) {
            return 0;
        }

        $destinatarios = $this->usuariosParaNotificarPendencia($origem);
        $enviadas = 0;

        foreach ($destinatarios as $destinatario) {
            if ($dedupeHoje) {
                $cacheKey = sprintf(
                    'aluno_pendente_transferencia_%d_%d_%s',
                    (int) $pendente->id,
                    (int) $destinatario->id,
                    now()->format('Y-m-d')
                );

                if (Cache::has($cacheKey)) {
                    continue;
                }

                Cache::put($cacheKey, true, now()->endOfDay());
            }

            $destinatario->notify(new SistemaNotification(
                titulo: 'Transferencia pendente de aluno',
                mensagem: sprintf(
                    'O aluno %s, CGM %s, esta com matricula pendente na escola %s. Gere o parecer de transferencia na unidade de origem.',
                    $origem->nome,
                    $origem->cgm,
                    $pendente->turma?->escola?->nome ?? 'destino nao identificado'
                ),
                url: route('filament.admin.pages.parecer-transferencia-aluno', ['aluno' => $origem->id]),
                label: 'Abrir Parecer de Transferencia',
                prioridade: 'alta',
                escopo: $origem->turma?->escola?->nome,
                metadata: [
                    'tipo' => 'aluno_transferencia_pendente',
                    'aluno_origem_id' => (int) $origem->id,
                    'aluno_pendente_id' => (int) $pendente->id,
                    'solicitante_id' => $solicitante?->id,
                ]
            ));

            $enviadas++;
        }

        return $enviadas;
    }

    public function usuariosParaNotificarPendencia(Aluno $origem): Collection
    {
        $origem->loadMissing('turma.escola');
        $escolaId = (int) ($origem->turma?->id_escola ?? 0);

        return User::query()
            ->with(['roles.permissions', 'permissions', 'escolas:id', 'professores:id,user_id,id_escola'])
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermissionLike('notificar status pendente')
                && $this->usuarioPertenceAEscola($user, $escolaId))
            ->unique('id')
            ->values();
    }

    private function professorVinculadoAoAluno(User $user, Aluno $aluno): bool
    {
        return $this->componentesPermitidosParaProfessor($user, $aluno) !== [];
    }

    private function professorIdsDoUsuario(User $user): array
    {
        return $user->professores()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function componentesComAvaliacaoDaTurma(Aluno $aluno): array
    {
        $aluno->loadMissing('turma');

        return DB::table('avaliacao_turma as at')
            ->join('avaliacao_pauta as ap', 'ap.avaliacao_id', '=', 'at.avaliacao_id')
            ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
            ->where('at.turma_id', (int) $aluno->id_turma)
            ->whereNotNull('p.componente_curricular_id')
            ->where(function ($query) use ($aluno): void {
                $query
                    ->whereNull('p.serie_id')
                    ->orWhere('p.serie_id', (int) ($aluno->turma?->id_serie ?? 0));
            })
            ->pluck('p.componente_curricular_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function usuarioPertenceAEscola(User $user, int $escolaId): bool
    {
        if ($escolaId <= 0) {
            return false;
        }

        $ids = collect([$user->id_escola])
            ->merge($user->escolas->pluck('id'))
            ->merge($user->professores->pluck('id_escola'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return in_array($escolaId, $ids, true);
    }
}

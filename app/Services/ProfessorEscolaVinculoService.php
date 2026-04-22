<?php

namespace App\Services;

use App\Models\Professor;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;

class ProfessorEscolaVinculoService
{
    public function sincronizarPorUsuario(User|int|null $user): void
    {
        $userModel = $user instanceof User
            ? $user->fresh()
            : User::query()->find($user);

        if (! $userModel) {
            return;
        }

        $escolasIds = $this->buscarEscolasPedagogicasDoUsuario($userModel->id);

        $userModel->escolas()->sync($escolasIds);
        $this->atualizarEscolaPrincipal($userModel, $escolasIds);
    }

    public function sincronizarPorUsuarios(array $userIds): void
    {
        collect($userIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->each(fn (int $userId) => $this->sincronizarPorUsuario($userId));
    }

    public function sincronizarPorProfessores(array $professorIds): void
    {
        $userIds = Professor::query()
            ->whereIn('id', collect($professorIds)->filter()->map(fn ($id) => (int) $id)->all())
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->sincronizarPorUsuarios($userIds);
    }

    private function buscarEscolasPedagogicasDoUsuario(int $userId): array
    {
        return TurmaComponenteProfessor::query()
            ->join('professores as p', 'p.id', '=', 'turma_componente_professor.professor_id')
            ->join('turmas as t', 't.id', '=', 'turma_componente_professor.turma_id')
            ->where('p.user_id', $userId)
            ->where('turma_componente_professor.tem_professor', true)
            ->select('t.id_escola')
            ->distinct()
            ->orderBy('t.id_escola')
            ->pluck('t.id_escola')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function atualizarEscolaPrincipal(User $user, array $escolasIds): void
    {
        $escolaPrincipal = null;

        if ($escolasIds !== []) {
            $escolaAtual = filled($user->id_escola) ? (int) $user->id_escola : null;
            $escolaPrincipal = in_array($escolaAtual, $escolasIds, true)
                ? $escolaAtual
                : $escolasIds[0];
        }

        if ((int) ($user->id_escola ?? 0) === (int) ($escolaPrincipal ?? 0)) {
            return;
        }

        $user->forceFill([
            'id_escola' => $escolaPrincipal,
        ])->save();
    }
}

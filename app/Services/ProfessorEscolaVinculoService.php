<?php

namespace App\Services;

use App\Models\Professor;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;

class ProfessorEscolaVinculoService
{
    public function sincronizarPorUsuario(User|int|null $user): void
    {
        // Fluxo: login Google/alteracoes pedagogicas chamam este metodo; ele recalcula escolas pelo vinculo turma-componente-professor, sincroniza escola_user e ajusta id_escola principal.
        $userModel = $user instanceof User
            ? $user->fresh()
            : User::query()->find($user);

        if (! $userModel) {
            return;
        }

        $escolasIds = $this->buscarEscolasPedagogicasDoUsuario($userModel->id);

        if ($escolasIds === []) {
            $escolasIds = $this->buscarEscolasCadastraisDoUsuario($userModel->id);
        }

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
        // Impacto: a fonte da verdade do professor e o pivot turma_componente_professor com tem_professor=true. Alterar essa consulta muda acesso a turmas, alunos e avaliacoes.
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

    private function buscarEscolasCadastraisDoUsuario(int $userId): array
    {
        return Professor::query()
            ->where('user_id', $userId)
            ->whereNotNull('id_escola')
            ->select('id_escola')
            ->distinct()
            ->orderBy('id_escola')
            ->pluck('id_escola')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function atualizarEscolaPrincipal(User $user, array $escolasIds): void
    {
        // Fluxo: se o usuario leciona em varias escolas, mantemos a escola atual quando ela ainda e valida; caso contrario usamos a primeira escola pedagogica encontrada.
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

<?php

namespace App\Policies;

use App\Models\Turma;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;

class TurmaPolicy
{
    /**
     * Ver qualquer turma.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Turmas');
    }

    public function export(User $user): bool
    {
        return ! $user->ehProfessor() && $this->viewAny($user);
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        if (app(\App\Services\PessoaScopeService::class)->podeConsultarTodaRede($user)) {
            return $query;
        }

        return app(UserService::class)->aplicarFiltroPorEscolaDoUsuarioEmTurma($query, $user);
    }

    /**
     * Ver uma turma específica.
     */
    public function view(User $user, Turma $turma): bool
    {
        return $user->hasPermissionTo('Listar Turmas')
            && app(UserService::class)->podeAcessarTurma($user, $turma);
    }

    /**
     * Criar turma.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Turmas');
    }

    /**
     * Editar turma.
     */
    public function update(User $user, Turma $turma): bool
    {
        return $user->hasPermissionTo('Editar Turmas')
            && app(UserService::class)->podeAcessarTurma($user, $turma);
    }

    /**
     * Excluir turma.
     */
    public function delete(User $user, Turma $turma): bool
    {
        return $user->hasPermissionTo('Excluir Turmas')
            && app(UserService::class)->podeAcessarTurma($user, $turma);
    }

    public function filterBySchool(User $user): bool
    {
        return $user->hasPermissionTo('Filtrar Turmas por Escola');
    }

    public function deleteBulk(User $user): bool
    {
        return $user->hasPermissionTo('Excluir Turmas em Massa');
    }

    public function editSchool(User $user): bool
    {
        return $user->hasPermissionTo('Editar Escola da Turma');
    }

    public function editData(User $user): bool
    {
        return $user->hasPermissionTo('Editar Dados da Turma');
    }
}

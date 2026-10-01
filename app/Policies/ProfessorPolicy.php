<?php

namespace App\Policies;

use App\Models\Professor;
use App\Models\User;
use App\Services\PessoaScopeService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;

class ProfessorPolicy
{
    /**
     * Ver qualquer professor.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Professores');
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        $query = $query->where('ativo', true);
        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user) || $scope->podeConsultarTodaRede($user)) {
            return $query;
        }

        $escolaIds = $scope->escolaIdsDosVinculos($user);

        if ($escolaIds !== []) {
            return $query->whereIn('id_escola', $escolaIds);
        }

        return app(UserService::class)->aplicarFiltroPorEscolaDoUsuarioEmTurma($query, $user);
    }

    /**
     * Ver um professor específico.
     */
    public function view(User $user, Professor $professor): bool
    {
        return $user->hasPermissionTo('Listar Professores')
            && app(UserService::class)->podeAcessarProfessor($user, $professor);
    }

    /**
     * Criar professor.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Professores');
    }

    /**
     * Atualizar professor.
     */
    public function update(User $user, Professor $professor): bool
    {
        return $user->hasPermissionTo('Editar Professores')
            && app(UserService::class)->podeAcessarProfessor($user, $professor);
    }

    /**
     * Deletar professor.
     */
    public function delete(User $user, Professor $professor): bool
    {
        return $user->hasPermissionTo('Excluir Professores')
            && app(UserService::class)->podeAcessarProfessor($user, $professor);
    }

    public function editMatricula(User $user): bool
    {
        return $user->hasPermissionTo('Editar Matricula do Professor');
    }

    public function editSchool(User $user): bool
    {
        return $user->hasPermissionTo('Editar Escola do Professor');
    }

    public function editName(User $user): bool
    {
        return $user->hasPermissionTo('Editar Nome do Professor');
    }

    public function editData(User $user): bool
    {
        return $user->hasPermissionTo('Editar Dados do Professor');
    }

    public function viewSpecializations(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Especializações de Professores');
    }

    public function editSpecializations(User $user): bool
    {
        return $user->hasPermissionTo('Editar Especializações de Professores');
    }

    public function viewDetails(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Detalhes de Professor');
    }

    public function viewProfessor(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Professores');
    }

    public function deleteBulk(User $user): bool
    {
        return $user->hasPermissionTo('Excluir Professores em Massa');
    }

    public function filterBySchool(User $user): bool
    {
        return $user->hasPermissionTo('Filtrar Professores por Escola');
    }

    public function filterBySerie(User $user): bool
    {
        return $user->hasPermissionTo('Filtrar Professores por Serie');
    }

    public function filterByComponent(User $user): bool
    {
        return $user->hasPermissionTo('Filtrar Professores por Componente');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo('Exportar Professores');
    }

    public function transfer(User $user): bool
    {
        return $user->hasPermissionTo('Transferir Professores');
    }

    public function deactivate(User $user): bool
    {
        return $user->hasPermissionTo('Desativar Professores');
    }
}

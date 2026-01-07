<?php

namespace App\Policies;

use App\Models\Aluno;
use App\Models\User;

class AlunoPolicy

{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Alunos');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Aluno $model): bool
    {
        return $user->hasPermissionTo('Listar Alunos');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Alunos');;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Aluno $model): bool
    {
        return $user->hasPermissionTo('Editar Alunos');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Aluno $model): bool
    {
        return $user->hasPermissionTo('Excluir Alunos');
    }

    public function exportarRelatorio(User $user, Aluno $model): bool
    {
        return $user->hasPermissionTo('Exportar Relatório de Alunos');
    }
}
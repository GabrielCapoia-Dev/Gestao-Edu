<?php

namespace App\Policies;

use App\Models\Setor;
use App\Models\User;

class SetorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Setores');
    }

    public function view(User $user, Setor $model): bool
    {
        return $user->hasPermissionTo('Listar Setores');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Setores');
    }

    public function update(User $user, Setor $model): bool
    {
        return $user->hasPermissionTo('Editar Setores');
    }

    public function delete(User $user, Setor $model): bool
    {
        return $user->hasPermissionTo('Excluir Setores');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('Excluir Setores em Massa');
    }
}

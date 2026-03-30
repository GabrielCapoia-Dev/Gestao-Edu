<?php

namespace App\Policies;

use App\Models\EquipeGestora;
use App\Models\User;

class EquipeGestoraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Equipe Gestora');
    }

    public function view(User $user, EquipeGestora $model): bool
    {
        return $user->hasPermissionTo('Listar Equipe Gestora');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Equipe Gestora');
    }

    public function update(User $user, EquipeGestora $model): bool
    {
        return $user->hasPermissionTo('Editar Equipe Gestora');
    }

    public function delete(User $user, EquipeGestora $model): bool
    {
        return $user->hasPermissionTo('Excluir Equipe Gestora');
    }
}

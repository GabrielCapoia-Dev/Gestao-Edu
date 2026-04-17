<?php

namespace App\Policies;

use App\Models\Alternativa;
use App\Models\User;

class AlternativaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Alternativas');
    }

    public function view(User $user, Alternativa $model): bool
    {
        return $user->hasPermissionTo('Listar Alternativas');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Alternativas');
    }

    public function update(User $user, Alternativa $model): bool
    {
        return $user->hasPermissionTo('Editar Alternativas');
    }

    public function delete(User $user, Alternativa $model): bool
    {
        return $user->hasPermissionTo('Excluir Alternativas');
    }
}

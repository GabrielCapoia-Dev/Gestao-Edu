<?php

namespace App\Policies;

use App\Models\Lotacao;
use App\Models\User;

class LotacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Escolas');
    }

    public function view(User $user, Lotacao $model): bool
    {
        return $user->hasPermissionTo('Listar Escolas');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Editar Escolas');
    }

    public function update(User $user, Lotacao $model): bool
    {
        return $user->hasPermissionTo('Editar Escolas');
    }

    public function delete(User $user, Lotacao $model): bool
    {
        return $user->hasPermissionTo('Editar Escolas');
    }
}

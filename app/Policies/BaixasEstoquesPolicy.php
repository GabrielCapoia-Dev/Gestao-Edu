<?php

namespace App\Policies;

use App\Models\BaixasEstoques;
use App\Models\User;

class BaixasEstoquesPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Gestão de Estoque');
    }

    public function view(User $user, BaixasEstoques $model): bool
    {
        return $this->viewAny($user);
    }
}
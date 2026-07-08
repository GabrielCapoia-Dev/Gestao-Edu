<?php

namespace App\Policies;

use App\Models\Estoque;
use App\Models\User;

class EstoquePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Gestão de Estoque');
    }

    public function view(User $user, Estoque $model): bool
    {
        return $this->viewAny($user);
    }
}
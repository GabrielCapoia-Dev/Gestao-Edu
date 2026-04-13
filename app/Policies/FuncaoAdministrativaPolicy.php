<?php

namespace App\Policies;

use App\Models\FuncaoAdministrativa;
use App\Models\User;

class FuncaoAdministrativaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionLike('listar funcoes administrativas');
    }

    public function view(User $user, FuncaoAdministrativa $model): bool
    {
        return $user->hasPermissionLike('listar funcoes administrativas');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionLike('criar funcoes administrativas');
    }

    public function update(User $user, FuncaoAdministrativa $model): bool
    {
        return $user->hasPermissionLike('editar funcoes administrativas');
    }

    public function delete(User $user, FuncaoAdministrativa $model): bool
    {
        return $user->hasPermissionLike('excluir funcoes administrativas');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionLike('excluir funcoes administrativas em massa');
    }
}

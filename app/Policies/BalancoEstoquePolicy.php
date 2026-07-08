<?php

namespace App\Policies;

use App\Models\BalancoEstoque;
use App\Models\User;

class BalancoEstoquePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Balanços de Estoque');
    }

    public function view(User $user, BalancoEstoque $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Balanços de Estoque');
    }

    public function update(User $user, BalancoEstoque $model): bool
    {
        return $user->hasPermissionTo('Editar Balanços de Estoque');
    }

    public function delete(User $user, BalancoEstoque $model): bool
    {
        return $user->hasPermissionTo('Excluir Balanços de Estoque');
    }
}
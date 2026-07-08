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

    public function start(User $user): bool
    {
        return $user->hasPermissionTo('Iniciar Balanços de Estoque');
    }

    public function postpone(User $user): bool
    {
        return $user->hasPermissionTo('Adiar Balanços de Estoque');
    }

    public function cancel(User $user): bool
    {
        return $user->hasPermissionTo('Cancelar Balanços de Estoque');
    }

    public function complete(User $user): bool
    {
        return $user->hasPermissionTo('Concluir Balanços de Estoque');
    }

    public function registerCount(User $user): bool
    {
        return $user->hasPermissionTo('Registrar Contagem de Balanços de Estoque');
    }
}
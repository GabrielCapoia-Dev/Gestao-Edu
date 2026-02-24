<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Pedido;

class PedidoPolicy

{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Pedidos');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Pedido $model): bool
    {
        return $user->hasPermissionTo('Listar Pedidos');

    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Pedidos');
        ;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Pedido $model): bool
    {
        return $user->hasPermissionTo('Editar Pedidos');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Pedido $model): bool
    {
        return $user->hasPermissionTo('Excluir Pedidos');
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, Pedido $model): bool
    // {
    //     return false;
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, Pedido $model): bool
    // {
    //     return false;
    // }
}


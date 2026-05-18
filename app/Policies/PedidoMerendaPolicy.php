<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PedidoMerenda;
use App\Services\UserSetorAccessService;

class PedidoMerendaPolicy

{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Pedidos: Merenda');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PedidoMerenda $model): bool
    {
        return $user->hasPermissionTo('Listar Pedidos: Merenda')
            && $this->podeAcessar($user, $model);

    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Pedidos: Merenda');
        ;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PedidoMerenda $model): bool
    {
        return $user->hasPermissionTo('Editar Pedidos: Merenda')
            && $this->podeAcessar($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PedidoMerenda $model): bool
    {
        return $user->hasPermissionTo('Excluir Pedidos: Merenda')
            && $this->podeAcessar($user, $model);
    }

    private function podeAcessar(User $user, PedidoMerenda $model): bool
    {
        return app(UserSetorAccessService::class)->canAccessSetor($user, $model->setor_id);
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, PedidoMerenda $model): bool
    // {
    //     return false;
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, PedidoMerenda $model): bool
    // {
    //     return false;
    // }
}

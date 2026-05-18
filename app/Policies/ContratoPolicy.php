<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Contrato;
use App\Services\UserSetorAccessService;

class ContratoPolicy

{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Contratos');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Contrato $model): bool
    {
        return $user->hasPermissionTo('Listar Contratos')
            && $this->podeAcessarContrato($user, $model);

    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Contratos');
        ;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Contrato $model): bool
    {
        return $user->hasPermissionTo('Editar Contratos')
            && $this->podeAcessarContrato($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Contrato $model): bool
    {
        return $user->hasPermissionTo('Excluir Contratos')
            && $this->podeAcessarContrato($user, $model);
    }

    private function podeAcessarContrato(User $user, Contrato $model): bool
    {
        $setorId = $model->setor_id ?: $model->empresaContratada?->setor_id;

        return app(UserSetorAccessService::class)->canAccessSetor($user, $setorId);
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, Contrato $model): bool
    // {
    //     return false;
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, Contrato $model): bool
    // {
    //     return false;
    // }
}

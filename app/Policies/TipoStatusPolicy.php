<?php

namespace App\Policies;

use App\Models\TipoStatus;
use App\Models\User;

class TipoStatusPolicy

{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Tipo Status');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TipoStatus $model): bool
    {
        return $user->hasPermissionTo('Listar Tipo Status');

    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Tipo Status');
        ;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TipoStatus $model): bool
    {
        return $user->hasPermissionTo('Editar Tipo Status');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TipoStatus $model): bool
    {
        return $user->hasPermissionTo('Excluir Tipo Status');
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, TipoStatus $model): bool
    // {
    //     return false;
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, TipoStatus $model): bool
    // {
    //     return false;
    // }
}

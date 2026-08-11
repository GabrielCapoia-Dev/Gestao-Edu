<?php

namespace App\Policies;

use App\Models\Serie;
use App\Models\User;

class SeriePolicy

{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Séries');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Serie $model): bool
    {
        return $user->hasPermissionTo('Listar Séries');

    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Séries');
        ;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Serie $model): bool
    {
        return $user->hasPermissionTo('Editar Séries');
    }

    /**
     * Determine whether the user can update models in bulk.
     */
    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo('Editar Séries');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Serie $model): bool
    {
        return $user->hasPermissionTo('Excluir Séries');
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, Serie $model): bool
    // {
    //     return false;
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, Serie $model): bool
    // {
    //     return false;
    // }
}

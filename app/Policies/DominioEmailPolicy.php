<?php

namespace App\Policies;

use App\Models\DominioEmail;
use App\Models\User;

class DominioEmailPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Dominios de Email');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DominioEmail $dominioEmail): bool
    {
        return $user->hasPermissionTo('Listar Dominios de Email');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Dominios de Email');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DominioEmail $dominioEmail): bool
    {
        return $user->hasPermissionTo('Editar Dominios de Email');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DominioEmail $dominioEmail): bool
    {
        return $user->hasPermissionTo('Excluir Dominios de Email');
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, DominioEmail $dominioEmail): bool
    // {
    //     return false;
    // }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, DominioEmail $dominioEmail): bool
    // {
    //     return false;
    // }
}

<?php

namespace App\Policies;

use App\Models\TipoManutencao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TipoManutencaoPolicy

{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Tipo Manutenção');
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TipoManutencao $model): bool
    {
        return $user->hasPermissionTo('Listar Tipo Manutenção');

    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Tipo Manutenção');
        ;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TipoManutencao $model): bool
    {
        return $user->hasPermissionTo('Editar Tipo Manutenção');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TipoManutencao $model): bool
    {
        return $user->hasPermissionTo('Excluir Tipo Manutenção');
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, TipoManutencao $model): bool
    // {
    //     return false;
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, TipoManutencao $model): bool
    // {
    //     return false;
    // }
}

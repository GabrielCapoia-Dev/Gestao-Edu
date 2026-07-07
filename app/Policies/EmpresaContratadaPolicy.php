<?php

namespace App\Policies;

use App\Models\EmpresaContratada;
use App\Models\User;
use App\Services\UserSetorAccessService;
use Illuminate\Database\Eloquent\Builder;

class EmpresaContratadaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Empresa Contratada');
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        return app(UserSetorAccessService::class)->applySetorScope($query, $user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmpresaContratada $empresaContratada): bool
    {
        return $user->hasPermissionTo('Listar Empresa Contratada')
            && $this->pertenceAoSetorDoUsuario($user, $empresaContratada);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Empresa Contratada');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmpresaContratada $empresaContratada): bool
    {
        return $user->hasPermissionTo('Editar Empresa Contratada')
            && $this->pertenceAoSetorDoUsuario($user, $empresaContratada);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmpresaContratada $empresaContratada): bool
    {
        return $user->hasPermissionTo('Excluir Empresa Contratada')
            && $this->pertenceAoSetorDoUsuario($user, $empresaContratada);
    }

    private function pertenceAoSetorDoUsuario(User $user, EmpresaContratada $empresaContratada): bool
    {
        return app(UserSetorAccessService::class)->canAccessSetor($user, $empresaContratada->setor_id);
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, EmpresaContratada $empresaContratada): bool
    // {
    //     return $user->hasPermissionTo('Empresa Contratada');
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, EmpresaContratada $empresaContratada): bool
    // {
    //     return $user->hasPermissionTo('Empresa Contratada');
    // }
}

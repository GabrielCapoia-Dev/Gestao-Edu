<?php

namespace App\Policies;

use App\Models\Setor;
use App\Models\User;
use App\Services\UserSetorAccessService;
use Illuminate\Database\Eloquent\Builder;

class SetorPolicy
{
    public const GLOBAL_SCOPE_PERMISSION = 'Acessar Escopo Global de Setores';

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Setores');
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        return app(UserSetorAccessService::class)
            ->applySetorScope($query->where('ativo', true), $user, 'id');
    }

    public function view(User $user, Setor $model): bool
    {
        return $user->hasPermissionTo('Listar Setores')
            && app(UserSetorAccessService::class)->canAccessSetor($user, $model->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Setores');
    }

    public function update(User $user, Setor $model): bool
    {
        return $user->hasPermissionTo('Editar Setores')
            && app(UserSetorAccessService::class)->canAccessSetor($user, $model->id);
    }

    public function delete(User $user, Setor $model): bool
    {
        return $user->hasPermissionTo('Excluir Setores')
            && app(UserSetorAccessService::class)->canAccessSetor($user, $model->id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('Excluir Setores em Massa');
    }

    public function accessGlobalScope(User $user): bool
    {
        return $user->hasPermissionTo(self::GLOBAL_SCOPE_PERMISSION);
    }

    public function configureAccessMatrix(User $user): bool
    {
        return $user->hasPermissionTo('Editar Setores')
            && ($user->hasRole('Admin') || $this->accessGlobalScope($user));
    }
}

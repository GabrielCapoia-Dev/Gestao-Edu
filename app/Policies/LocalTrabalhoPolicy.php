<?php

namespace App\Policies;

use App\Models\LocalTrabalho;
use App\Models\User;
use App\Services\UserSetorAccessService;
use Illuminate\Database\Eloquent\Builder;

class LocalTrabalhoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Escolas');
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        return app(UserSetorAccessService::class)
            ->applySetorScope($query->where('ativo', true), $user);
    }

    public function view(User $user, LocalTrabalho $model): bool
    {
        return $user->hasPermissionTo('Listar Escolas');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Escolas');
    }

    public function update(User $user, LocalTrabalho $model): bool
    {
        return $user->hasPermissionTo('Editar Escolas');
    }

    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo('Editar Escolas');
    }

    public function delete(User $user, LocalTrabalho $model): bool
    {
        return $user->hasPermissionTo('Excluir Escolas');
    }

    public function editCodigo(User $user): bool
    {
        return $user->hasPermissionTo('Editar Codigo da Escola');
    }
}

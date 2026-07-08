<?php

namespace App\Policies;

use App\Models\BalancoInventario;
use App\Models\User;
use App\Services\Inventario\InventarioContextService;
use Illuminate\Database\Eloquent\Builder;

class BalancoInventarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Balanços de Inventário');
    }

    public function view(User $user, BalancoInventario $model): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $inventariosVisiveis = app(InventarioContextService::class)
            ->queryInventariosVisiveis($user)
            ->select('inventarios.id');

        return BalancoInventario::query()
            ->whereKey($model->getKey())
            ->whereIn('inventario_id', $inventariosVisiveis)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Balanços de Inventário');
    }

    public function update(User $user, BalancoInventario $model): bool
    {
        return $user->hasPermissionTo('Editar Balanços de Inventário')
            && $this->view($user, $model);
    }

    public function delete(User $user, BalancoInventario $model): bool
    {
        return $user->hasPermissionTo('Excluir Balanços de Inventário')
            && $this->view($user, $model);
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        $inventariosVisiveis = app(InventarioContextService::class)
            ->queryInventariosVisiveis($user)
            ->select('inventarios.id');

        return $query->whereIn('inventario_id', $inventariosVisiveis);
    }

    public function start(User $user): bool
    {
        return $user->hasPermissionTo('Iniciar Balanços de Inventário');
    }

    public function postpone(User $user): bool
    {
        return $user->hasPermissionTo('Adiar Balanços de Inventário');
    }

    public function cancel(User $user): bool
    {
        return $user->hasPermissionTo('Cancelar Balanços de Inventário');
    }

    public function complete(User $user): bool
    {
        return $user->hasPermissionTo('Concluir Balanços de Inventário');
    }

    public function registerCount(User $user): bool
    {
        return $user->hasPermissionTo('Registrar Contagem de Balanços de Inventário');
    }
}
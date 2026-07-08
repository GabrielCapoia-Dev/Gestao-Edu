<?php

namespace App\Policies;

use App\Models\Inventario;
use App\Models\User;
use App\Services\Inventario\InventarioContextService;
use Illuminate\Database\Eloquent\Builder;

class InventarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Inventários');
    }

    public function view(User $user, Inventario $model): bool
    {
        if (! $this->accessGestao($user)) {
            return false;
        }

        return app(InventarioContextService::class)
            ->queryInventariosVisiveis($user)
            ->whereKey($model->getKey())
            ->exists();
    }

    public function accessGestao(User $user): bool
    {
        return $user->hasPermissionTo('Listar Gestão de Inventário')
            || $user->hasPermissionTo('Listar Inventários');
    }

    public function accessPanorama(User $user): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(InventarioContextService::class)->ehGestorGeral($user);
    }

    public function accessBaixasWithContext(User $user): bool
    {
        if (! $this->accessGestao($user)) {
            return false;
        }

        return app(InventarioContextService::class)
            ->resolverInventario($user, request()->integer('inventario')) !== null;
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        return app(InventarioContextService::class)->queryInventariosVisiveis($user);
    }

    public function exportReports(User $user): bool
    {
        return $user->hasPermissionTo('Exportar Relatórios');
    }

    public function export(User $user, Inventario $model): bool
    {
        if (! $this->exportReports($user)) {
            return false;
        }

        return $this->view($user, $model);
    }

    public function exportNetwork(User $user): bool
    {
        if (! $this->exportReports($user)) {
            return false;
        }

        return app(InventarioContextService::class)->ehGestorGeral($user);
    }

    public function exportRomaneio(User $user): bool
    {
        return $this->exportNetwork($user);
    }
}
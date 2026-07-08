<?php

namespace App\Policies;

use App\Models\InventarioPedido;
use App\Models\User;
use App\Services\Inventario\InventarioContextService;
use App\Services\Inventario\InventarioPedidoService;
use Illuminate\Database\Eloquent\Builder;

class InventarioPedidoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Pedidos de Inventário');
    }

    public function view(User $user, InventarioPedido $model): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(InventarioPedidoService::class)
            ->queryTabela($user)
            ->whereKey($model->getKey())
            ->exists();
    }

    public function create(User $user): bool
    {
        if (! $user->hasPermissionTo('Criar Pedidos de Inventário')) {
            return false;
        }

        return ! app(InventarioContextService::class)->ehGestorGeral($user);
    }

    public function update(User $user, InventarioPedido $model): bool
    {
        return $user->hasPermissionTo('Editar Pedidos de Inventário')
            && $this->view($user, $model);
    }

    public function delete(User $user, InventarioPedido $model): bool
    {
        return $user->hasPermissionTo('Excluir Pedidos de Inventário')
            && $this->view($user, $model);
    }

    public function approve(User $user): bool
    {
        return $user->hasPermissionTo('Aprovar Pedidos de Inventário');
    }

    public function confer(User $user): bool
    {
        return $user->hasPermissionTo('Conferir Pedidos de Inventário');
    }

    public function generateRomaneio(User $user): bool
    {
        return $user->hasPermissionTo('Gerar Romaneios de Inventário');
    }

    public function applyViewAnyScope(User $user, Builder $query): Builder
    {
        return app(InventarioPedidoService::class)->queryTabela($user);
    }
}
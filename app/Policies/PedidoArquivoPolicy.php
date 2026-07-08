<?php

namespace App\Policies;

use App\Models\PedidoArquivo;
use App\Models\Pedido;
use App\Models\User;
use App\Services\PedidoService;

class PedidoArquivoPolicy
{
    public function download(User $user, PedidoArquivo $arquivo): bool
    {
        if (! $user->hasPermissionTo('Exportar Arquivos Pedido')) {
            return false;
        }

        $pedidoId = $arquivo->pedido_id ?: $arquivo->pedido?->getKey();

        if (! $pedidoId) {
            return false;
        }

        if ($user->hasRole('Admin') || $user->hasPermissionTo('Listar Todos os Pedidos')) {
            return true;
        }

        return app(PedidoService::class)
            ->queryPorPerfil(Pedido::query()->whereKey($pedidoId), $user)
            ->exists();
    }

    public function exportImages(User $user, Pedido $pedido): bool
    {
        if (! $user->hasPermissionTo('Exportar Arquivos Pedido')) {
            return false;
        }

        if ($user->hasRole('Admin') || $user->hasPermissionTo('Listar Todos os Pedidos')) {
            return true;
        }

        return app(PedidoService::class)
            ->queryPorPerfil(Pedido::query()->whereKey($pedido->getKey()), $user)
            ->exists();
    }
}

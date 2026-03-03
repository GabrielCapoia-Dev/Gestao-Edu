<?php

namespace App\Observers;

use App\Models\Pedido;
use App\Models\User;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Notifications\SistemaNotification;

class PedidoObserver
{
    /**
     * Handle the Pedido "created" event.
     */
    public function created(Pedido $pedido): void
    {
        //
    }

    /**
     * Handle the Pedido "updated" event.
     */
    public function updated(Pedido $pedido): void
    {
        if (
            $pedido->wasChanged('nivel_prioridade') &&
            $pedido->nivel_prioridade === NivelEmergenciaPedido::EMERGENCIAL
        ) {

            $usuarios = User::permission('Visualizar Notificação: Pedidos Emergenciais')->get();

            foreach ($usuarios as $user) {
                $user->notify(
                    new SistemaNotification(
                        titulo: 'Pedido Emergencial',
                        mensagem: "O pedido {$pedido->numero_protocolo} foi marcado como Emergencial.",
                        url: route('filament.admin.resources.pedidos.edit', $pedido),
                    )
                );
            }
        }
    }

    /**
     * Handle the Pedido "deleted" event.
     */
    public function deleted(Pedido $pedido): void
    {
        //
    }

    /**
     * Handle the Pedido "restored" event.
     */
    public function restored(Pedido $pedido): void
    {
        //
    }

    /**
     * Handle the Pedido "force deleted" event.
     */
    public function forceDeleted(Pedido $pedido): void
    {
        //
    }
}

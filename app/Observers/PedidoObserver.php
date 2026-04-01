<?php

namespace App\Observers;

use App\Models\Pedido;
use App\Models\User;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Notifications\SistemaNotification;
use App\Models\TipoStatus;
use Illuminate\Support\Facades\Auth;


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


        if ($pedido->wasChanged('tipo_status_id')) {

            $statusAtual = $pedido->tipoStatus;
            $statusReaberto = TipoStatus::where('nome', 'Reaberto')->first();


            if (
                $statusReaberto &&
                $pedido->tipo_status_id === $statusReaberto->id
            ) {

                $usuarios = User::permission('Visualizar Notificação: Pedido Reaberto')->get();

                $solicitante = $pedido->solicitante;

                if ($solicitante) {

                    $solicitante->notify(
                        new SistemaNotification(
                            titulo: 'Pedido Reaberto',
                            mensagem: "Seu pedido {$pedido->numero_protocolo} foi reaberto e encaminhado ao setor responsável.",
                            url: route('filament.admin.resources.pedidos.edit', $pedido),
                        )
                    );
                }

                foreach ($usuarios as $user) {

                    if ($solicitante && $user->id === $solicitante->id) {
                        continue;
                    }

                    $solicitante->notify(
                        new SistemaNotification(
                            titulo: 'Atualização no Pedido',
                            mensagem: "O status do seu pedido {$pedido->numero_protocolo} foi atualizado para \n\"{$statusAtual->nome}\".",
                            url: route('filament.admin.resources.pedidos.edit', $pedido),
                        )
                    );
                }

                return;
            }

            $solicitante = $pedido->solicitante;


            if ($solicitante && $statusAtual) {

                $solicitante->notify(
                    new SistemaNotification(
                        titulo: 'Atualização no Pedido',
                        mensagem: "O status do seu pedido {$pedido->numero_protocolo} foi atualizado para \n\"{$statusAtual->nome}\".",
                        url: route('filament.admin.resources.pedidos.view', $pedido),
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

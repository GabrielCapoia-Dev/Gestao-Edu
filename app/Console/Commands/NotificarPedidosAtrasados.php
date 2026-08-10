<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Models\User;
use App\Notifications\SistemaNotification;
use App\Services\PedidoService;
use App\Support\UserActorSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotificarPedidosAtrasados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notificar-pedidos-atrasados';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hoje = now()->startOfDay();

        $pedidosAtrasados = Pedido::whereNull('data_entrega')
            ->whereNotNull('data_prevista')
            ->whereDate('data_prevista', '<', $hoje)
            ->get();

        if ($pedidosAtrasados->isEmpty()) {
            return;
        }

        $usuarios = User::permission('Visualizar Notificação: Pedidos Atrasados')->get();
        $pedidoService = app(PedidoService::class);

        foreach ($pedidosAtrasados as $pedido) {

            // 🔑 Chave única por pedido por dia
            $cacheKey = 'pedido_atraso_notificado_' . $pedido->id . '_' . now()->format('Y-m-d');

            if (Cache::has($cacheKey)) {
                continue; // já notificou hoje
            }

            foreach ($usuarios as $user) {
                if (
                    ! UserActorSnapshot::canReceiveNotification($user)
                    || ! $pedidoService->registroVisivelNoPerfil($pedido, $user)
                ) {
                    continue;
                }

                $user->notify(
                    new SistemaNotification(
                        titulo: 'Pedido Atrasado',
                        mensagem: "Pedido {$pedido->numero_protocolo} está vencido desde {$pedido->data_prevista->format('d/m/Y')}.",
                        url: route('filament.admin.resources.pedidos.edit', $pedido)
                    )
                );
            }

            // ⏳ Expira no final do dia
            Cache::put($cacheKey, true, now()->endOfDay());
        }
    }
}

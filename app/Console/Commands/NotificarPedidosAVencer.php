<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Models\User;
use App\Notifications\SistemaNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotificarPedidosAVencer extends Command
{
    protected $signature = 'app:notificar-pedidos-a-vencer';
    protected $description = 'Notifica pedidos que estão próximos do vencimento';

    public function handle()
    {
        $intervalos = [7, 5, 3, 1];
        $hoje = now()->startOfDay();

        $usuarios = User::permission('Visualizar Notificação: Vencimento de Pedidos')->get();

        foreach ($intervalos as $dias) {

            $dataAlvo = $hoje->copy()->addDays($dias);

            $pedidos = Pedido::whereNull('data_entrega')
                ->whereNotNull('data_prevista')
                ->whereDate('data_prevista', $dataAlvo)
                ->get();

            foreach ($pedidos as $pedido) {

                $cacheKey = "pedido_avencer_{$pedido->id}_{$dias}_" . now()->format('Y-m-d');

                if (Cache::has($cacheKey)) {
                    continue;
                }

                foreach ($usuarios as $user) {
                    $user->notify(
                        new SistemaNotification(
                            titulo: 'Pedido Próximo do Vencimento',
                            mensagem: "Pedido {$pedido->numero_protocolo} vence em {$dias} dia(s) ({$pedido->data_prevista->format('d/m/Y')}).",
                            url: route('filament.admin.resources.pedidos.edit', $pedido)
                        )
                    );
                }

                Cache::put($cacheKey, true, now()->endOfDay());
            }
        }
    }
}
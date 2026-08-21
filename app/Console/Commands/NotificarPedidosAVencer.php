<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Models\User;
use App\Notifications\SistemaNotification;
use App\Services\PedidoNotificationRecipientService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NotificarPedidosAVencer extends Command
{
    protected $signature = 'app:notificar-pedidos-a-vencer';
    protected $description = 'Notifica, de forma resumida, pedidos próximos do vencimento';

    public function handle(): int
    {
        // Três marcos são suficientes para acompanhamento sem excesso de alertas.
        $intervalos = [7, 3, 1];
        $hoje = now()->startOfDay();
        $recipientService = app(PedidoNotificationRecipientService::class);
        $alertas = [];

        foreach ($intervalos as $dias) {
            $pedidos = Pedido::query()
                ->whereNull('data_entrega')
                ->whereNotNull('data_prevista')
                ->whereDate('data_prevista', $hoje->copy()->addDays($dias))
                ->get();

            foreach ($pedidos as $pedido) {
                foreach ($recipientService->destinatarios($pedido) as $user) {
                    $alertas[$user->id]['user'] = $user;
                    $alertas[$user->id]['pedidos'][$dias][] = $pedido;
                }
            }
        }

        foreach ($alertas as $alerta) {
            /** @var User $user */
            $user = $alerta['user'];

            foreach ($alerta['pedidos'] as $dias => $pedidos) {
                $cacheKey = "pedidos_a_vencer_resumo_{$user->id}_{$dias}_".$hoje->format('Y-m-d');

                if (Cache::has($cacheKey)) {
                    continue;
                }

                $pedidos = collect($pedidos);
                $user->notify(
                    new SistemaNotification(
                        titulo: 'Pedidos Próximos do Vencimento',
                        mensagem: $this->mensagemResumo($pedidos, (int) $dias),
                        url: route('filament.admin.resources.pedidos.index'),
                    )
                );

                Cache::put($cacheKey, true, now()->endOfDay());
            }
        }

        return self::SUCCESS;
    }

    /** @param Collection<int, Pedido> $pedidos */
    private function mensagemResumo(Collection $pedidos, int $dias): string
    {
        $quantidade = $pedidos->count();
        $protocolos = $pedidos
            ->pluck('numero_protocolo')
            ->take(5)
            ->implode(', ');
        $complemento = $quantidade > 5 ? ' e mais '.($quantidade - 5) : '';

        return "{$quantidade} pedido(s) da sua escola vencem em {$dias} dia(s): {$protocolos}{$complemento}.";
    }
}

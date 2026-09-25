<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Models\User;
use App\Notifications\SistemaNotification;
use App\Services\PedidoNotificationRecipientService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NotificarPedidosAtrasados extends Command
{
    protected $signature = 'app:notificar-pedidos-atrasados';
    protected $description = 'Notifica, de forma resumida e periódica, pedidos atrasados';

    public function handle(): int
    {
        $hoje = now()->startOfDay();
        $recipientService = app(PedidoNotificationRecipientService::class);
        $alertas = [];

        $pedidosAtrasados = Pedido::query()
            ->whereNull('data_entrega')
            ->whereNotNull('data_prevista')
            ->where('data_prevista', '<', $hoje->toDateString())
            ->get()
            ->filter(fn (Pedido $pedido): bool => $this->deveNotificarAtraso($pedido, $hoje));

        foreach ($pedidosAtrasados as $pedido) {
            foreach ($recipientService->destinatarios($pedido) as $user) {
                $alertas[$user->id]['user'] = $user;
                $alertas[$user->id]['pedidos'][] = $pedido;
            }
        }

        foreach ($alertas as $alerta) {
            /** @var User $user */
            $user = $alerta['user'];
            $cacheKey = "pedidos_atrasados_resumo_{$user->id}_".$hoje->format('Y-m-d');

            if (Cache::has($cacheKey)) {
                continue;
            }

            $pedidos = collect($alerta['pedidos']);
            $user->notify(
                new SistemaNotification(
                    titulo: 'Resumo de Pedidos Atrasados',
                    mensagem: $this->mensagemResumo($pedidos),
                    url: route('filament.admin.resources.pedidos.index'),
                )
            );

            Cache::put($cacheKey, true, now()->endOfDay());
        }

        return self::SUCCESS;
    }

    private function deveNotificarAtraso(Pedido $pedido, \Carbon\Carbon $hoje): bool
    {
        $diasAtrasado = (int) $pedido->data_prevista->copy()->startOfDay()->diffInDays($hoje);

        return $diasAtrasado > 0 && $diasAtrasado % 2 === 0;
    }

    /** @param Collection<int, Pedido> $pedidos */
    private function mensagemResumo(Collection $pedidos): string
    {
        $quantidade = $pedidos->count();
        $protocolos = $pedidos
            ->pluck('numero_protocolo')
            ->take(5)
            ->implode(', ');
        $complemento = $quantidade > 5 ? ' e mais '.($quantidade - 5) : '';

        return "{$quantidade} pedido(s) atrasado(s) da sua escola precisam de atenção: {$protocolos}{$complemento}.";
    }
}

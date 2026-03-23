<?php

namespace Database\Seeders;

use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\PedidoMerenda;
use App\Models\PedidoMerendaItem;
use App\Models\Enums\StatusPedidoMerenda;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PedidoMerendaSeeder extends Seeder
{
    public function run(): void
    {
        $contratos = Contrato::where('ativo', true)
            ->with('contratoItens')
            ->get();

        if ($contratos->isEmpty()) {
            $this->command->error('Nenhum contrato ativo. Rode o ContratoSeeder primeiro.');
            return;
        }

        $totalPedidos = 0;
        $totalItens   = 0;

        // Gera entre 3 e 8 pedidos por contrato
        foreach ($contratos as $contrato) {
            $contratoItensDisponiveis = $contrato->contratoItens
                ->filter(fn($ci) => $ci->saldo_disponivel > 0)
                ->values();

            if ($contratoItensDisponiveis->isEmpty()) {
                continue;
            }

            $qtdPedidos = rand(3, 8);

            for ($p = 0; $p < $qtdPedidos; $p++) {
                // Recarrega saldos a cada pedido para refletir reservas/baixas anteriores
                $contratoItensDisponiveis = ContratoItem::whereIn(
                    'id',
                    $contratoItensDisponiveis->pluck('id')
                )->get()->filter(fn($ci) => $ci->saldo_disponivel > 0)->values();

                if ($contratoItensDisponiveis->isEmpty()) {
                    break;
                }

                // 70% entregue, 30% aguardando — distribui situações reais
                $status = (rand(1, 10) <= 7)
                    ? StatusPedidoMerenda::Entregue
                    : StatusPedidoMerenda::Aguardando;

                $pedido = PedidoMerenda::create([
                    'status'      => $status,
                    'observacoes' => "Pedido gerado via seeder - Contrato {$contrato->numero_contrato}",
                    'criado_por'  => 'Seeder',
                ]);

                // Sorteia entre 1 e 5 itens do contrato para este pedido
                $qtdItensNoPedido = min(rand(1, 5), $contratoItensDisponiveis->count());
                $itensSorteados   = $contratoItensDisponiveis->shuffle()->take($qtdItensNoPedido);

                foreach ($itensSorteados as $ci) {
                    $saldo = $ci->saldo_disponivel;

                    if ($saldo <= 0) {
                        continue;
                    }

                    // Pede entre 5% e 40% do saldo disponível
                    $percentual        = rand(5, 40) / 100;
                    $quantidadePedida  = round($saldo * $percentual, 3);
                    $quantidadePedida  = max(0.001, $quantidadePedida);

                    PedidoMerendaItem::create([
                        'pedido_merenda_id' => $pedido->id,
                        'contrato_item_id'  => $ci->id,
                        'quantidade_pedida' => $quantidadePedida,
                    ]);

                    // Aplica a baixa diretamente no ContratoItem
                    if ($status === StatusPedidoMerenda::Entregue) {
                        $ci->increment('quantidade_utilizada', $quantidadePedida);
                    } else {
                        $ci->increment('quantidade_reservada', $quantidadePedida);
                    }

                    $totalItens++;
                }

                $totalPedidos++;
            }
        }

        $this->command->info("✔ {$totalPedidos} pedidos criados com {$totalItens} itens no total.");
    }
}
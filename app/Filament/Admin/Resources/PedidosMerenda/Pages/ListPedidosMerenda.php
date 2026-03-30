<?php

namespace App\Filament\Admin\Resources\PedidosMerenda\Pages;

use App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource;
use App\Models\PedidoMerendaItem;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListPedidosMerenda extends ListRecords
{
    protected static string $resource = PedidosMerendaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo Pedido'),
        ];
    }

    // -------------------------------------------------------------------------
    // Salvar quantidade pedida (edição do pedido ainda em aberto)
    // Chamado pelo $wire.salvarQuantidade() no blade modal-itens.
    // -------------------------------------------------------------------------

    public function salvarQuantidade(int $pedidoItemId, float $novaQuantidade): void
    {
        $pedidoItem        = PedidoMerendaItem::with('contratoItem')->findOrFail($pedidoItemId);
        $quantidadeAnterior = (float) $pedidoItem->quantidade_pedida;
        $jaEntregue        = (float) $pedidoItem->quantidade_entregue;

        if ($novaQuantidade < 0) {
            Notification::make()->title('Quantidade inválida.')->danger()->send();
            return;
        }

        // Não permite reduzir a quantidade pedida abaixo do que já foi entregue
        if ($novaQuantidade < $jaEntregue) {
            Notification::make()
                ->title("Não é possível reduzir abaixo da quantidade já entregue ({$jaEntregue}).")
                ->danger()
                ->send();
            return;
        }

        $ci           = $pedidoItem->contratoItem;
        $saldoMaximo  = (float) $ci->saldo_disponivel + $quantidadeAnterior;

        if ($novaQuantidade > $saldoMaximo) {
            Notification::make()
                ->title("Quantidade excede o saldo disponível ({$saldoMaximo}).")
                ->danger()
                ->send();
            return;
        }

        $diferenca = $novaQuantidade - $quantidadeAnterior;

        DB::transaction(function () use ($pedidoItem, $ci, $novaQuantidade, $diferenca) {
            $pedidoItem->update(['quantidade_pedida' => $novaQuantidade]);
            $ci->increment('quantidade_reservada', $diferenca);
        });

        Notification::make()
            ->title('Quantidade atualizada com sucesso.')
            ->success()
            ->send();
    }

    // -------------------------------------------------------------------------
    // Registrar entrega parcial de um item
    // Chamado pelo $wire.salvarEntregaParcial() no blade modal-itens.
    // -------------------------------------------------------------------------

    public function salvarEntregaParcial(int $pedidoItemId, float $quantidadeEntregaAgora): void
    {
        $pedidoItem  = PedidoMerendaItem::with(['contratoItem.item', 'pedido'])->findOrFail($pedidoItemId);
        $pendente    = (float) $pedidoItem->quantidade_pendente;

        if ($quantidadeEntregaAgora <= 0) {
            Notification::make()->title('Informe uma quantidade maior que zero.')->warning()->send();
            return;
        }

        if ($quantidadeEntregaAgora > $pendente) {
            Notification::make()
                ->title("Quantidade excede o saldo pendente de entrega ({$pendente}).")
                ->danger()
                ->send();
            return;
        }

        $ci     = $pedidoItem->contratoItem;
        $pedido = $pedidoItem->pedido;

        DB::transaction(function () use ($pedidoItem, $ci, $pedido, $quantidadeEntregaAgora) {
            // 1. Atualiza quantidade entregue no item do pedido
            $pedidoItem->increment('quantidade_entregue', $quantidadeEntregaAgora);

            // 2. Move reserva → utilizado no contrato
            $ci->decrement('quantidade_reservada', $quantidadeEntregaAgora);
            $ci->increment('quantidade_utilizada', $quantidadeEntregaAgora);

            // 3. Entrada no estoque central
            $estoque = \App\Models\Estoque::firstOrCreate(
                ['item_id' => $ci->item_id],
                ['quantidade' => 0]
            );

            $estoque->entrada(
                quantidade: $quantidadeEntregaAgora,
                pedidoMerendaId: $pedido->id,
                observacao: "Entrega parcial do pedido #{$pedido->id}",
            );

            // 4. Recalcula status do pedido (Aguardando / ParcialmenteEntregue / Entregue)
            $pedido->recalcularStatus();
        });

        Notification::make()
            ->title('Entrega registrada com sucesso.')
            ->success()
            ->send();
    }
}
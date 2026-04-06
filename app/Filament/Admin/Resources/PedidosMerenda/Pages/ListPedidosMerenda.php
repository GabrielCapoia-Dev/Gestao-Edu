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

    public function salvarQuantidade(int $pedidoItemId, float $novaQuantidade): void
    {
        $pedidoItem = PedidoMerendaItem::with('contratoItem')->findOrFail($pedidoItemId);
        $quantidadeAnterior = (float) $pedidoItem->quantidade_pedida;
        $jaEntregue = (float) $pedidoItem->quantidade_entregue;

        if ($novaQuantidade < 0) {
            Notification::make()->title('Quantidade inválida.')->danger()->send();

            return;
        }

        if ($novaQuantidade < $jaEntregue) {
            Notification::make()
                ->title("Não é possível reduzir abaixo da quantidade já entregue ({$jaEntregue}).")
                ->danger()
                ->send();

            return;
        }

        $contratoItem = $pedidoItem->contratoItem;
        $saldoMaximo = (float) $contratoItem->saldo_disponivel + $quantidadeAnterior;

        if ($novaQuantidade > $saldoMaximo) {
            Notification::make()
                ->title("Quantidade excede o saldo disponível ({$saldoMaximo}).")
                ->danger()
                ->send();

            return;
        }

        $diferenca = $novaQuantidade - $quantidadeAnterior;

        DB::transaction(function () use ($pedidoItem, $contratoItem, $novaQuantidade, $diferenca) {
            $pedidoItem->update(['quantidade_pedida' => $novaQuantidade]);
            $contratoItem->increment('quantidade_reservada', $diferenca);
        });

        Notification::make()
            ->title('Quantidade atualizada com sucesso.')
            ->success()
            ->send();
    }

    public function salvarEntregaParcial(int $pedidoItemId, float $quantidadeEntregaAgora): void
    {
        $pedidoItem = PedidoMerendaItem::with(['contratoItem.item', 'pedido'])->findOrFail($pedidoItemId);
        $pendente = (float) $pedidoItem->quantidade_pendente;

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

        $contratoItem = $pedidoItem->contratoItem;
        $pedido = $pedidoItem->pedido;

        try {
            DB::transaction(function () use ($pedidoItem, $contratoItem, $pedido, $quantidadeEntregaAgora) {
                $pedidoItem->increment('quantidade_entregue', $quantidadeEntregaAgora);

                $contratoItem->decrement('quantidade_reservada', $quantidadeEntregaAgora);
                $contratoItem->increment('quantidade_utilizada', $quantidadeEntregaAgora);

                $estoque = \App\Models\Estoque::firstOrCreate(
                    ['item_id' => $contratoItem->item_id],
                    ['quantidade' => 0]
                );

                $estoque->entrada(
                    quantidade: $quantidadeEntregaAgora,
                    pedidoMerendaId: $pedido->id,
                    observacao: "Entrega parcial do pedido #{$pedido->id}",
                );

                $pedido->recalcularStatus();
            });
        } catch (\DomainException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Entrega registrada com sucesso.')
            ->success()
            ->send();
    }
}

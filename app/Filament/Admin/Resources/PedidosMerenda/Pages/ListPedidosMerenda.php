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

    /**
     * Chamado pelo $wire.salvarQuantidade() no blade modal-itens.
     * Ajusta quantidade_pedida e recalcula quantidade_reservada no contrato_item.
     */
    public function salvarQuantidade(int $pedidoItemId, float $novaQuantidade): void
    {
        $pedidoItem = PedidoMerendaItem::with('contratoItem')->findOrFail($pedidoItemId);
        $quantidadeAnterior = (float) $pedidoItem->quantidade_pedida;

        if ($novaQuantidade <= 0) {
            Notification::make()
                ->title('A quantidade deve ser maior que zero.')
                ->danger()
                ->send();
            return;
        }

        $ci = $pedidoItem->contratoItem;

        // Saldo máximo permitido = saldo disponível atual + o que este item já reservou
        $saldoMaximo = (float) $ci->saldo_disponivel + $quantidadeAnterior;

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
}
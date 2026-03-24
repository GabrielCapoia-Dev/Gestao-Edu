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
     * Aceita 0 = item removido do pedido (mantém registro, devolve reserva).
     */
    public function salvarQuantidade(int $pedidoItemId, float $novaQuantidade): void
    {
        $pedidoItem = PedidoMerendaItem::with('contratoItem')->findOrFail($pedidoItemId);
        $quantidadeAnterior = (float) $pedidoItem->quantidade_pedida;

        // Negativo nunca é válido; zero = remoção do item
        if ($novaQuantidade < 0) {
            Notification::make()->title('Quantidade inválida.')->danger()->send();
            return;
        }

        $ci = $pedidoItem->contratoItem;
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

        $mensagem = $novaQuantidade === 0.0
            ? 'Item removido. Saldo devolvido ao contrato.'
            : 'Quantidade atualizada com sucesso.';

        Notification::make()->title($mensagem)->success()->send();
    }
}
<?php

namespace App\Filament\Admin\Resources\PedidosMerenda\Tables;

use App\Models\Enums\StatusPedidoMerenda;
use App\Models\PedidoMerenda;
use App\Models\PedidoMerendaItem;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\DB;

class PedidoMerendaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50])
            ->defaultSort('created_at', 'desc')
            ->columns(static::columns())
            ->filters(static::filters(), layout: FiltersLayout::AboveContent)
            ->recordActions(static::recordActions());
    }

    // -------------------------------------------------------------------------
    // Columns
    // -------------------------------------------------------------------------

    public static function columns(): array
    {
        return [
            TextColumn::make('id')
                ->label('#')
                ->sortable(),

            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->color(fn($state) => match ($state) {
                    StatusPedidoMerenda::Aguardando => 'warning',
                    StatusPedidoMerenda::Entregue   => 'success',
                    StatusPedidoMerenda::Cancelado  => 'danger',
                    default                         => 'gray',
                })
                ->formatStateUsing(fn($state) => $state?->label()),

            TextColumn::make('itens_count')
                ->label('Itens')
                ->counts('itens')
                ->sortable(),

            TextColumn::make('observacoes')
                ->label('Observações')
                ->limit(60)
                ->placeholder('—'),

            TextColumn::make('criado_por')
                ->label('Criado por')
                ->placeholder('—'),

            TextColumn::make('created_at')
                ->label('Criado em')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ];
    }

    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Status')
                ->options(
                    collect(StatusPedidoMerenda::cases())
                        ->mapWithKeys(fn($case) => [$case->value => $case->label()])
                        ->toArray()
                ),
        ];
    }

    // -------------------------------------------------------------------------
    // Record Actions
    // -------------------------------------------------------------------------

    public static function recordActions(): array
    {
        return [
            // ── Ver / Editar Itens ─────────────────────────────────────────
            Action::make('verItens')
                ->label('Itens')
                ->icon('heroicon-o-list-bullet')
                ->color('info')
                ->slideOver()
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalHeading(fn(PedidoMerenda $record) => "Itens do Pedido #{$record->id}")
                ->modalDescription(fn(PedidoMerenda $record) => match ($record->status) {
                    StatusPedidoMerenda::Aguardando => 'Você pode ajustar as quantidades dos itens abaixo.',
                    StatusPedidoMerenda::Entregue   => 'Este pedido já foi entregue. Somente visualização.',
                    StatusPedidoMerenda::Cancelado  => 'Este pedido foi cancelado. Somente visualização.',
                    default                         => null,
                })
                ->modalContent(function (PedidoMerenda $record) {
                    $itens = $record->itens()
                        ->with([
                            'contratoItem.item',
                            'contratoItem.contrato.empresaContratada',
                        ])
                        ->get()
                        ->map(function (PedidoMerendaItem $pedidoItem) {
                            $ci = $pedidoItem->contratoItem;

                            return [
                                'pedido_item_id'   => $pedidoItem->id,
                                'contrato_item_id' => $ci->id,
                                'item_nome'        => $ci->item->nome,
                                'unidade'          => $ci->item->unidade_medida->value,
                                'empresa'          => $ci->contrato->empresaContratada->nome,
                                'numero_contrato'  => $ci->contrato->numero_contrato,
                                'saldo_atual'      => (float) $ci->saldo_disponivel,
                                'saldo_com_pedido' => (float) $ci->saldo_disponivel + (float) $pedidoItem->quantidade_pedida,
                                'quantidade'       => (float) $pedidoItem->quantidade_pedida,
                            ];
                        });

                    $editavel = $record->status === StatusPedidoMerenda::Aguardando;

                    return view('components.pedidos-merenda.modal-itens', [
                        'itens'    => $itens,
                        'editavel' => $editavel,
                        'pedido'   => $record,
                    ]);
                }),
            ActionGroup::make([

                // ── Marcar como Entregue ───────────────────────────────────────
                Action::make('marcarEntregue')
                    ->label('Entregue')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar entrega do pedido')
                    ->modalDescription(
                        fn(PedidoMerenda $record) =>
                        "Confirma a entrega do Pedido #{$record->id}? " .
                            "A quantidade reservada de cada item será movida para quantidade utilizada. " .
                            "Esta ação não pode ser desfeita."
                    )
                    ->modalSubmitActionLabel('Confirmar entrega')
                    ->visible(fn(PedidoMerenda $record) => $record->status === StatusPedidoMerenda::Aguardando)
                    ->action(function (PedidoMerenda $record) {
                        static::processarEntrega($record);
                    }),

                // ── Cancelar Pedido ────────────────────────────────────────────
                Action::make('cancelarPedido')
                    ->label('Cancelado')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar pedido')
                    ->modalDescription(
                        fn(PedidoMerenda $record) =>
                        "Confirma o cancelamento do Pedido #{$record->id}? " .
                            "Todo o saldo reservado será devolvido aos contratos. " .
                            "Esta ação não pode ser desfeita."
                    )
                    ->modalSubmitActionLabel('Confirmar cancelamento')
                    ->visible(fn(PedidoMerenda $record) => $record->status === StatusPedidoMerenda::Aguardando)
                    ->action(function (PedidoMerenda $record) {
                        static::processarCancelamento($record);
                    }),
            ])
        ];
    }

    // -------------------------------------------------------------------------
    // Processar Entrega
    // -------------------------------------------------------------------------

    public static function processarEntrega(PedidoMerenda $pedido): void
    {
        $itens = $pedido->itens()->with('contratoItem')->get();

        DB::transaction(function () use ($pedido, $itens) {
            foreach ($itens as $pedidoItem) {
                $quantidade = (float) $pedidoItem->quantidade_pedida;

                // Itens zerados (removidos) não movimentam saldo
                if ($quantidade <= 0) {
                    continue;
                }

                $ci = $pedidoItem->contratoItem;

                // Move reserva → utilizado
                $ci->decrement('quantidade_reservada', $quantidade);
                $ci->increment('quantidade_utilizada', $quantidade);
            }

            $pedido->update(['status' => StatusPedidoMerenda::Entregue]);
        });

        Notification::make()
            ->title("Pedido #{$pedido->id} marcado como entregue.")
            ->success()
            ->send();
    }

    // -------------------------------------------------------------------------
    // Processar Cancelamento
    // -------------------------------------------------------------------------

    public static function processarCancelamento(PedidoMerenda $pedido): void
    {
        $itens = $pedido->itens()->with('contratoItem')->get();

        DB::transaction(function () use ($pedido, $itens) {
            foreach ($itens as $pedidoItem) {
                $quantidade = (float) $pedidoItem->quantidade_pedida;

                if ($quantidade <= 0) {
                    continue;
                }

                $ci = $pedidoItem->contratoItem;

                // Devolve reserva ao saldo disponível
                $ci->decrement('quantidade_reservada', $quantidade);
            }

            $pedido->update(['status' => StatusPedidoMerenda::Cancelado]);
        });

        Notification::make()
            ->title("Pedido #{$pedido->id} cancelado. Saldo devolvido aos contratos.")
            ->warning()
            ->send();
    }

    // -------------------------------------------------------------------------
    // Salvar quantidade de item individual (chamado pelo Livewire do modal)
    // -------------------------------------------------------------------------

    public static function salvarQuantidade(int $pedidoItemId, float $novaQuantidade): void
    {
        $pedidoItem = PedidoMerendaItem::with('contratoItem')->findOrFail($pedidoItemId);
        $quantidadeAnterior = (float) $pedidoItem->quantidade_pedida;

        if ($novaQuantidade < 0) {
            Notification::make()->title('Quantidade inválida.')->danger()->send();
            return;
        }

        $ci = $pedidoItem->contratoItem;
        $saldoComPedido = (float) $ci->saldo_disponivel + $quantidadeAnterior;

        if ($novaQuantidade > $saldoComPedido) {
            Notification::make()
                ->title("Quantidade excede o saldo disponível ({$saldoComPedido}).")
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
            ? 'Item removido do pedido. Saldo devolvido ao contrato.'
            : 'Quantidade atualizada.';

        Notification::make()->title($mensagem)->success()->send();
    }
}

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
                ->color(fn ($state) => match ($state) {
                    StatusPedidoMerenda::Aguardando           => 'warning',
                    StatusPedidoMerenda::ParcialmenteEntregue => 'info',
                    StatusPedidoMerenda::Entregue             => 'success',
                    StatusPedidoMerenda::Cancelado            => 'danger',
                    default                                   => 'gray',
                })
                ->formatStateUsing(fn ($state) => $state?->label()),

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
                        ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
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
                ->modalHeading(fn (?PedidoMerenda $record) => $record
                    ? "Itens do Pedido #{$record->id}"
                    : 'Itens do Pedido'
                )
                ->modalDescription(fn (?PedidoMerenda $record) => match ($record?->status) {
                    StatusPedidoMerenda::Aguardando           => 'Você pode ajustar as quantidades e registrar entregas parciais.',
                    StatusPedidoMerenda::ParcialmenteEntregue => 'Pedido com entrega parcial em andamento. Registre as próximas entregas abaixo.',
                    StatusPedidoMerenda::Entregue             => 'Este pedido foi totalmente entregue. Somente visualização.',
                    StatusPedidoMerenda::Cancelado            => 'Este pedido foi cancelado. Somente visualização.',
                    default                                   => null,
                })
                ->modalContent(function (?PedidoMerenda $record) {
                    if (! $record) {
                        return view('components.pedidos-merenda.modal-itens', [
                            'itens'    => collect(),
                            'editavel' => false,
                            'pedido'   => null,
                        ]);
                    }

                    // Recarrega o pedido do banco para garantir status atualizado
                    $record->refresh();

                    $itens = $record->itens()
                        ->with([
                            'contratoItem.item',
                            'contratoItem.contrato.empresaContratada',
                        ])
                        ->get()
                        ->map(function (PedidoMerendaItem $pedidoItem) {
                            $ci = $pedidoItem->contratoItem;

                            return [
                                'pedido_item_id'      => $pedidoItem->id,
                                'contrato_item_id'    => $ci->id,
                                'item_nome'           => $ci->item->nome,
                                'unidade'             => $ci->item->unidade_medida->value,
                                'empresa'             => $ci->contrato->empresaContratada->nome,
                                'numero_contrato'     => $ci->contrato->numero_contrato,
                                'saldo_atual'         => (float) $ci->saldo_disponivel,
                                'saldo_com_pedido'    => (float) $ci->saldo_disponivel + (float) $pedidoItem->quantidade_pedida,
                                'quantidade'          => (float) $pedidoItem->quantidade_pedida,
                                'quantidade_entregue' => (float) $pedidoItem->quantidade_entregue,
                                'quantidade_pendente' => (float) $pedidoItem->quantidade_pendente,
                            ];
                        });

                    // Editável somente enquanto não estiver totalmente entregue ou cancelado
                    $editavel = in_array($record->status, [
                        StatusPedidoMerenda::Aguardando,
                        StatusPedidoMerenda::ParcialmenteEntregue,
                    ]);

                    return view('components.pedidos-merenda.modal-itens', [
                        'itens'    => $itens,
                        'editavel' => $editavel,
                        'pedido'   => $record,
                    ]);
                }),

            ActionGroup::make([

                // ── Cancelar Pedido ────────────────────────────────────────────
                Action::make('cancelarPedido')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar pedido')
                    ->modalDescription(fn (?PedidoMerenda $record) => $record
                        ? "Confirma o cancelamento do Pedido #{$record->id}? " .
                          "O saldo pendente de entrega será devolvido aos contratos. " .
                          "Quantidades já entregues permanecem no estoque. " .
                          "Esta ação não pode ser desfeita."
                        : ''
                    )
                    ->modalSubmitActionLabel('Confirmar cancelamento')
                    ->visible(fn (?PedidoMerenda $record) => $record && in_array($record->status, [
                        StatusPedidoMerenda::Aguardando,
                        StatusPedidoMerenda::ParcialmenteEntregue,
                    ]))
                    ->action(function (?PedidoMerenda $record) {
                        if (! $record) {
                            return;
                        }

                        static::processarCancelamento($record);
                    }),
            ]),
        ];
    }

    // -------------------------------------------------------------------------
    // Processar Cancelamento
    // Devolve apenas o saldo PENDENTE (quantidade_pedida - quantidade_entregue).
    // O que já foi entregue fica no estoque e no contrato como utilizado.
    // -------------------------------------------------------------------------

    public static function processarCancelamento(PedidoMerenda $pedido): void
    {
        $itens = $pedido->itens()->with('contratoItem')->get();

        DB::transaction(function () use ($pedido, $itens) {
            foreach ($itens as $pedidoItem) {
                $pendente = (float) $pedidoItem->quantidade_pendente;

                if ($pendente <= 0) {
                    continue;
                }

                // Devolve apenas o que ainda não foi entregue
                $pedidoItem->contratoItem->decrement('quantidade_reservada', $pendente);
            }

            $pedido->update(['status' => StatusPedidoMerenda::Cancelado]);
        });

        Notification::make()
            ->title("Pedido #{$pedido->id} cancelado. Saldo pendente devolvido aos contratos.")
            ->warning()
            ->send();
    }
}
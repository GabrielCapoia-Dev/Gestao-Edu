<?php

namespace App\Filament\Admin\Resources\PedidosMerenda\Tables;

use App\Models\ContratoItem;
use App\Models\Enums\StatusPedidoMerenda;
use App\Models\PedidoMerenda;
use App\Models\PedidoMerendaItem;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                                // saldo disponível se este pedido não existisse
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
        ];
    }

    // -------------------------------------------------------------------------
    // Método estático chamado pelo Livewire do modal para salvar ajuste
    // -------------------------------------------------------------------------

    public static function salvarQuantidade(int $pedidoItemId, float $novaQuantidade): void
    {
        $pedidoItem = PedidoMerendaItem::with('contratoItem')->findOrFail($pedidoItemId);
        $quantidadeAnterior = (float) $pedidoItem->quantidade_pedida;

        // Quantidade negativa não é permitida; zero = item removido (mantém registro)
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

            // Ajusta a reserva pela diferença:
            //   positiva → aumentou o pedido, reserva sobe
            //   negativa → diminuiu/zerou, reserva desce (saldo devolvido ao contrato)
            $ci->increment('quantidade_reservada', $diferenca);
        });

        $mensagem = $novaQuantidade === 0.0
            ? 'Item removido do pedido. Saldo devolvido ao contrato.'
            : 'Quantidade atualizada.';

        Notification::make()->title($mensagem)->success()->send();
    }
}
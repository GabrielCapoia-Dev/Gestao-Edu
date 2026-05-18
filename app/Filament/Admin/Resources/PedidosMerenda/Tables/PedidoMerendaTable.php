<?php

namespace App\Filament\Admin\Resources\PedidosMerenda\Tables;

use App\Models\Enums\StatusPedidoMerenda;
use App\Models\PedidoMerenda;
use App\Models\PedidoMerendaItem;
use App\Services\UserSetorAccessService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PedidoMerendaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => app(UserSetorAccessService::class)
                ->applySetorScope($query->withCount('itens'), Auth::user()))
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->defaultSort('created_at', 'desc')
            ->columns(static::columns())
            ->filters(static::filters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(12)
            ->striped()
            ->recordActions(static::recordActions());
    }

    public static function columns(): array
    {
        return [
            TextColumn::make('id')
                ->label('#')
                ->sortable()
                ->searchable(),

            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->sortable()
                ->color(fn ($state) => match ($state) {
                    StatusPedidoMerenda::Aguardando => 'warning',
                    StatusPedidoMerenda::ParcialmenteEntregue => 'info',
                    StatusPedidoMerenda::Entregue => 'success',
                    StatusPedidoMerenda::Cancelado => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn ($state) => $state?->label()),

            TextColumn::make('itens_count')
                ->label('Itens')
                ->badge()
                ->sortable(),

            TextColumn::make('observacoes')
                ->label('Observacoes')
                ->searchable()
                ->limit(70)
                ->placeholder('-')
                ->toggleable(),

            TextColumn::make('criado_por')
                ->label('Criado por')
                ->searchable()
                ->sortable()
                ->placeholder('-'),

            TextColumn::make('setor.nome_completo')
                ->label('Setor')
                ->placeholder('-')
                ->toggleable(),

            TextColumn::make('created_at')
                ->label('Criado em')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->description(fn (PedidoMerenda $record) => 'Atualizado em ' . $record->updated_at?->format('d/m/Y H:i')),
        ];
    }

    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Status')
                ->columnSpan(3)
                ->multiple()
                ->options(
                    collect(StatusPedidoMerenda::cases())
                        ->mapWithKeys(fn (StatusPedidoMerenda $status) => [$status->value => $status->label()])
                        ->toArray()
                ),

            SelectFilter::make('criado_por')
                ->label('Criado por')
                ->columnSpan(3)
                ->searchable()
                ->options(fn () => PedidoMerenda::query()
                    ->whereNotNull('criado_por')
                    ->orderBy('criado_por')
                    ->pluck('criado_por', 'criado_por')
                    ->toArray()),

            SelectFilter::make('setor_id')
                ->label('Setor')
                ->columnSpan(3)
                ->options(fn () => app(UserSetorAccessService::class)->optionsForSelect(Auth::user()))
                ->searchable()
                ->preload(),

            Filter::make('periodo_criacao')
                ->label('Periodo de criacao')
                ->columnSpan(6)
                ->columns(2)
                ->schema([
                    DatePicker::make('data_inicio')
                        ->label('De')
                        ->columnSpan(1),
                    DatePicker::make('data_fim')
                        ->label('Ate')
                        ->columnSpan(1),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['data_inicio'] ?? null),
                            fn (Builder $builder) => $builder->whereDate('created_at', '>=', $data['data_inicio'])
                        )
                        ->when(
                            filled($data['data_fim'] ?? null),
                            fn (Builder $builder) => $builder->whereDate('created_at', '<=', $data['data_fim'])
                        );
                }),
        ];
    }

    private static function resolveRecord(?PedidoMerenda $record): ?PedidoMerenda
    {
        if (! $record) {
            return null;
        }

        return PedidoMerenda::find($record->id);
    }

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
                ->modalHeading(function (?PedidoMerenda $record) {
                    $pedido = static::resolveRecord($record);

                    return $pedido ? "Itens do Pedido #{$pedido->id}" : 'Itens do Pedido';
                })
                ->modalDescription(function (?PedidoMerenda $record) {
                    $pedido = static::resolveRecord($record);

                    return match ($pedido?->status) {
                        StatusPedidoMerenda::Aguardando => 'Voce pode ajustar as quantidades e registrar entregas parciais.',
                        StatusPedidoMerenda::ParcialmenteEntregue => 'Pedido com entrega parcial em andamento. Registre as proximas entregas abaixo.',
                        StatusPedidoMerenda::Entregue => 'Este pedido foi totalmente entregue. Somente visualizacao.',
                        StatusPedidoMerenda::Cancelado => 'Este pedido foi cancelado. Somente visualizacao.',
                        default => null,
                    };
                })
                ->modalContent(function (?PedidoMerenda $record) {
                    $pedido = static::resolveRecord($record);

                    if (! $pedido) {
                        return view('components.pedidos-merenda.modal-itens', [
                            'itens' => collect(),
                            'editavel' => false,
                            'pedido' => null,
                        ]);
                    }

                    $itens = $pedido->itens()
                        ->with([
                            'contratoItem.item',
                            'contratoItem.contrato.empresaContratada',
                        ])
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->get()
                        ->map(function (PedidoMerendaItem $pedidoItem) {
                            $ci = $pedidoItem->contratoItem;

                            return [
                                'pedido_item_id' => $pedidoItem->id,
                                'contrato_item_id' => $ci->id,
                                'item_nome' => $ci->item->nome,
                                'unidade' => $ci->item->unidade_medida->value,
                                'empresa' => $ci->contrato->empresaContratada->nome,
                                'numero_contrato' => $ci->contrato->numero_contrato,
                                'saldo_atual' => (float) $ci->saldo_disponivel,
                                'saldo_com_pedido' => (float) $ci->saldo_disponivel + (float) $pedidoItem->quantidade_pedida,
                                'quantidade' => (float) $pedidoItem->quantidade_pedida,
                                'quantidade_entregue' => (float) $pedidoItem->quantidade_entregue,
                                'quantidade_pendente' => (float) $pedidoItem->quantidade_pendente,
                            ];
                        });

                    $editavel = in_array($pedido->status, [
                        StatusPedidoMerenda::Aguardando,
                        StatusPedidoMerenda::ParcialmenteEntregue,
                    ]);

                    return view('components.pedidos-merenda.modal-itens', [
                        'itens' => $itens,
                        'editavel' => $editavel,
                        'pedido' => $pedido,
                    ]);
                }),

            ActionGroup::make([
                Action::make('cancelarPedido')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar pedido')
                    ->modalDescription(function (?PedidoMerenda $record) {
                        $pedido = static::resolveRecord($record);

                        return $pedido
                            ? "Confirma o cancelamento do Pedido #{$pedido->id}? O saldo pendente de entrega sera devolvido aos contratos. Quantidades ja entregues permanecem no estoque. Esta acao nao pode ser desfeita."
                            : '';
                    })
                    ->modalSubmitActionLabel('Confirmar cancelamento')
                    ->visible(function (?PedidoMerenda $record) {
                        $pedido = static::resolveRecord($record);

                        return $pedido && in_array($pedido->status, [
                            StatusPedidoMerenda::Aguardando,
                            StatusPedidoMerenda::ParcialmenteEntregue,
                        ]);
                    })
                    ->action(function (?PedidoMerenda $record) {
                        $pedido = static::resolveRecord($record);

                        if (! $pedido) {
                            return;
                        }

                        static::processarCancelamento($pedido);
                    }),
            ]),
        ];
    }

    public static function processarCancelamento(PedidoMerenda $pedido): void
    {
        app(UserSetorAccessService::class)->assertCanUseSetor(Auth::user(), $pedido->setor_id);

        $itens = $pedido->itens()->with('contratoItem')->get();

        DB::transaction(function () use ($pedido, $itens) {
            foreach ($itens as $pedidoItem) {
                $pendente = (float) $pedidoItem->quantidade_pendente;

                if ($pendente <= 0) {
                    continue;
                }

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

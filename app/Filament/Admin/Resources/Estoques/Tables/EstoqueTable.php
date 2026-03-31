<?php

namespace App\Filament\Admin\Resources\Estoques\Tables;

use App\Models\Estoque;
use App\Models\Item;
use App\Models\EstoqueMovimentacao;
use App\Models\Enums\TipoMovimentacao;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use App\Models\Enums\TipoItem;

class EstoqueTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->defaultSort('quantidade', 'desc')
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
            TextColumn::make('item.nome')
                ->label('Item')
                ->sortable()
                ->searchable(),

            TextColumn::make('item.unidade_medida')
                ->label('Unidade')
                ->formatStateUsing(fn($state) => strtoupper($state?->value ?? $state))
                ->badge()
                ->color('gray'),

            TextColumn::make('item.tipo_item')
                ->label('Categoria')
                ->formatStateUsing(fn($state) => $state?->label())
                ->badge()
                ->color('info'),

            TextColumn::make('quantidade')
                ->label('Quantidade em Estoque')
                ->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.')
                ->sortable()
                ->color(fn(Estoque $record): string => match (true) {
                    (float) $record->quantidade <= 0   => 'danger',
                    (float) $record->quantidade <= 10  => 'warning',
                    default                            => 'success',
                }),

            TextColumn::make('movimentacoes_count')
                ->label('Movimentações')
                ->counts('movimentacoes')
                ->sortable()
                ->badge()
                ->color('gray'),

            TextColumn::make('updated_at')
                ->label('Última atualização')
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
            SelectFilter::make('tipo_item')
                ->label('Categoria')
                ->options(
                    collect(TipoItem::cases())->mapWithKeys(fn($case) => [
                        $case->value => $case->label(),
                    ])
                )
                ->query(function ($query, $value) {
                    $query->whereHas(
                        'item',
                        fn($q) =>
                        $q->where('tipo_item', $value)
                    );
                }),
        ];
    }

    // -------------------------------------------------------------------------
    // Record Actions
    // -------------------------------------------------------------------------

    public static function recordActions(): array
    {
        return [
            Action::make('verMovimentacoes')
                ->label('Movimentações')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->slideOver()
                ->modalWidth('3xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalHeading(fn(Estoque $record) => "Movimentações — {$record->item->nome}")
                ->modalContent(function (Estoque $record) {
                    $movimentacoes = $record->movimentacoes()
                        ->with('pedidoMerenda')
                        ->orderByDesc('created_at')
                        ->limit(50)
                        ->get()
                        ->map(function (EstoqueMovimentacao $mov) {
                            return [
                                'id'               => $mov->id,
                                'tipo'             => $mov->tipo,
                                'tipo_label'       => $mov->tipo === TipoMovimentacao::Entrada ? 'Entrada' : 'Saída',
                                'quantidade'       => (float) $mov->quantidade,
                                'pedido_id'        => $mov->pedido_merenda_id,
                                'observacao'       => $mov->observacao,
                                'registrado_por'   => $mov->registrado_por,
                                'data'             => $mov->created_at->format('d/m/Y H:i'),
                            ];
                        });

                    return view('components.estoque.modal-movimentacoes', [
                        'estoque'        => $record,
                        'movimentacoes'  => $movimentacoes,
                    ]);
                }),

            ActionGroup::make([
                Action::make('registrarEntrada')
                    ->label('Registrar Entrada')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('quantidade')
                            ->label('Quantidade')
                            ->numeric()
                            ->minValue(0.001)
                            ->required()
                            ->step(0.001),
                        \Filament\Forms\Components\Textarea::make('observacao')
                            ->label('Observação')
                            ->rows(2)
                            ->nullable(),
                    ])
                    ->action(function (Estoque $record, array $data) {
                        static::processarEntradaManual($record, (float) $data['quantidade'], $data['observacao'] ?? null);
                    }),

                Action::make('registrarSaida')
                    ->label('Registrar Saída')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('quantidade')
                            ->label('Quantidade')
                            ->numeric()
                            ->minValue(0.001)
                            ->required()
                            ->step(0.001),
                        \Filament\Forms\Components\Textarea::make('observacao')
                            ->label('Observação')
                            ->rows(2)
                            ->nullable(),
                    ])
                    ->action(function (Estoque $record, array $data) {
                        static::processarSaidaManual($record, (float) $data['quantidade'], $data['observacao'] ?? null);
                    }),
            ]),
        ];
    }

    // -------------------------------------------------------------------------
    // Entrada manual (sem vínculo com pedido)
    // -------------------------------------------------------------------------

    public static function processarEntradaManual(Estoque $estoque, float $quantidade, ?string $observacao): void
    {
        DB::transaction(function () use ($estoque, $quantidade, $observacao) {
            $estoque->entrada($quantidade, null, $observacao ?? 'Entrada manual');
        });

        Notification::make()
            ->title("Entrada de {$quantidade} registrada no estoque.")
            ->success()
            ->send();
    }

    // -------------------------------------------------------------------------
    // Saída manual
    // -------------------------------------------------------------------------

    public static function processarSaidaManual(Estoque $estoque, float $quantidade, ?string $observacao): void
    {
        if ((float) $estoque->quantidade < $quantidade) {
            Notification::make()
                ->title("Saldo insuficiente. Estoque atual: {$estoque->quantidade}.")
                ->danger()
                ->send();
            return;
        }

        DB::transaction(function () use ($estoque, $quantidade, $observacao) {
            $estoque->saida($quantidade, null, $observacao ?? 'Saída manual');
        });

        Notification::make()
            ->title("Saída de {$quantidade} registrada no estoque.")
            ->warning()
            ->send();
    }
}

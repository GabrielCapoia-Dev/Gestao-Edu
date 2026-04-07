<?php

namespace App\Filament\Admin\Resources\HistoricoBaixasInventario\Tables;

use App\Models\Enums\MotivoBaixa;
use App\Models\Enums\TipoItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HistoricoBaixasInventarioTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->defaultSort('created_at', 'desc')
            ->columns(static::columns())
            ->filters(static::filters());
    }

    public static function columns(): array
    {
        return [
            TextColumn::make('estoque.item.nome')
                ->label('Item')
                ->searchable()
                ->sortable()
                ->weight('bold'),
            TextColumn::make('estoque.inventario.escola.nome')
                ->label('Escola')
                ->toggleable(),
            TextColumn::make('estoque.item.tipo_item')
                ->label('Categoria')
                ->badge()
                ->formatStateUsing(fn ($state) => $state?->label() ?? $state),
            TextColumn::make('quantidade')
                ->label('Quantidade Baixada')
                ->numeric(decimalPlaces: 3)
                ->sortable(),
            TextColumn::make('saldo_anterior')
                ->label('Saldo Anterior')
                ->numeric(decimalPlaces: 3)
                ->sortable(),
            TextColumn::make('saldo_posterior')
                ->label('Saldo Posterior')
                ->numeric(decimalPlaces: 3)
                ->sortable(),
            TextColumn::make('motivo')
                ->label('Motivo')
                ->badge()
                ->formatStateUsing(fn ($state) => $state?->label() ?? $state)
                ->sortable(),
            TextColumn::make('descricao')
                ->label('Descrição')
                ->limit(80)
                ->wrap(),
            TextColumn::make('registrado_por')
                ->label('Registrado por')
                ->placeholder('—'),
            TextColumn::make('created_at')
                ->label('Data da Baixa')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ];
    }

    public static function filters(): array
    {
        return [
            SelectFilter::make('categoria')
                ->label('Categoria')
                ->options(
                    collect(TipoItem::cases())
                        ->mapWithKeys(fn (TipoItem $tipo) => [$tipo->value => $tipo->label()])
                        ->toArray()
                )
                ->query(function ($query, array $data) {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('estoque.item', fn ($itemQuery) => $itemQuery->where('tipo_item', $data['value']));
                }),
            SelectFilter::make('motivo')
                ->label('Motivo')
                ->options(
                    collect(MotivoBaixa::cases())
                        ->mapWithKeys(fn (MotivoBaixa $motivo) => [$motivo->value => $motivo->label()])
                        ->toArray()
                ),
        ];
    }
}

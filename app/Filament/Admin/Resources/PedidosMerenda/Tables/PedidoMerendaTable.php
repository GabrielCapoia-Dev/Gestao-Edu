<?php

namespace App\Filament\Admin\Resources\PedidosMerenda\Tables;

use App\Models\Enums\StatusPedidoMerenda;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;

class PedidoMerendaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50])
            ->defaultSort('created_at', 'desc')
            ->columns(static::columns())
            ->filters(static::filters(), layout: FiltersLayout::AboveContent);
    }

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
}
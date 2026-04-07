<?php

namespace App\Filament\Admin\Resources\BaixasInventario\Tables;

use App\Models\Enums\MotivoBaixa;
use App\Models\Enums\TipoItem;
use App\Models\InventarioEstoque;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BaixasInventarioTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->defaultSort('updated_at', 'desc')
            ->columns(static::columns())
            ->filters(static::filters())
            ->recordActions(static::recordActions());
    }

    public static function columns(): array
    {
        return [
            TextColumn::make('item.nome')
                ->label('Item')
                ->searchable()
                ->sortable()
                ->weight('bold'),
            TextColumn::make('item.tipo_item')
                ->label('Categoria')
                ->formatStateUsing(fn ($state) => $state?->label() ?? $state)
                ->badge(),
            TextColumn::make('item.unidade_medida')
                ->label('Unidade')
                ->formatStateUsing(fn ($state) => strtoupper($state?->value ?? (string) $state)),
            TextColumn::make('quantidade')
                ->label('Saldo no Inventário')
                ->sortable()
                ->numeric(decimalPlaces: 3),
            TextColumn::make('inventario.escola.nome')
                ->label('Escola')
                ->toggleable(),
            TextColumn::make('updated_at')
                ->label('Atualizado em')
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

                    return $query->whereHas('item', fn ($itemQuery) => $itemQuery->where('tipo_item', $data['value']));
                }),
        ];
    }

    public static function recordActions(): array
    {
        return [
            Action::make('baixa')
                ->label('Baixa')
                ->icon('heroicon-o-arrow-trending-down')
                ->color('danger')
                ->visible(fn (InventarioEstoque $record) => (float) $record->quantidade > 0)
                ->modalHeading(fn (InventarioEstoque $record) => "Registrar baixa - {$record->item->nome}")
                ->schema([
                    TextInput::make('quantidade')
                        ->label('Quantidade de baixa')
                        ->numeric()
                        ->required()
                        ->minValue(0.001)
                        ->step('0.001'),
                    Select::make('motivo')
                        ->label('Motivo')
                        ->options(
                            collect(MotivoBaixa::cases())
                                ->mapWithKeys(fn (MotivoBaixa $motivo) => [$motivo->value => $motivo->label()])
                                ->toArray()
                        )
                        ->native(false)
                        ->required(),
                    Textarea::make('descricao')
                        ->label('Descrição')
                        ->required()
                        ->rows(4)
                        ->maxLength(1000),
                ])
                ->action(function (InventarioEstoque $record, array $data) {
                    try {
                        $record->registrarBaixa(
                            (float) $data['quantidade'],
                            MotivoBaixa::from((string) $data['motivo']),
                            trim((string) $data['descricao']),
                        );

                        Notification::make()
                            ->title('Baixa registrada com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\BaixasEstoque\Tables;

use App\Models\Enums\TipoItem;
use App\Models\Estoque;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BaixasEstoqueTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->defaultSort('updated_at', 'desc')
            ->columns(static::columns())
            ->filters(static::filters(), layout: FiltersLayout::AboveContent)
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
                ->formatStateUsing(fn($state) => $state?->label() ?? $state)
                ->badge(),

            TextColumn::make('item.unidade_medida')
                ->label('Unidade')
                ->formatStateUsing(fn($state) => strtoupper($state?->value ?? (string) $state)),

            TextColumn::make('quantidade')
                ->label('Saldo em Estoque')
                ->sortable()
                ->numeric(decimalPlaces: 3),

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
                        ->mapWithKeys(fn(TipoItem $tipo) => [$tipo->value => $tipo->label()])
                        ->toArray()
                )
                ->query(function ($query, array $data) {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('item', fn($itemQuery) => $itemQuery->where('tipo_item', $data['value']));
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
                ->visible(fn(Estoque $record) => (float) $record->quantidade > 0)
                ->modalHeading(fn(Estoque $record) => "Registrar baixa - {$record->item->nome}")
                ->modalDescription(fn(Estoque $record) => 'Informe a quantidade a baixar, o motivo e confirme a saída do item do estoque.')
                ->schema([
                    TextInput::make('quantidade')
                        ->label('Quantidade de baixa')
                        ->numeric()
                        ->required()
                        ->minValue(0.001)
                        ->step('0.001'),
                    Textarea::make('motivo')
                        ->label('Motivo da baixa')
                        ->required()
                        ->rows(4)
                        ->maxLength(1000),
                ])
                ->action(function (Estoque $record, array $data) {
                    $quantidade = (float) $data['quantidade'];
                    $motivo = trim((string) $data['motivo']);

                    if ($quantidade > (float) $record->quantidade) {
                        Notification::make()
                            ->title('Quantidade de baixa maior que o saldo em estoque.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->registrarBaixa($quantidade, $motivo);

                    Notification::make()
                        ->title('Baixa registrada com sucesso.')
                        ->success()
                        ->send();
                }),
        ];
    }
}

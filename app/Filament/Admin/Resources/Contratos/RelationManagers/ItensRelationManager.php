<?php

namespace App\Filament\Admin\Resources\Contratos\RelationManagers;

use App\Models\Item;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use App\Models\Enums\TipoItemContrato;
use Dom\Text;
use Filament\Schemas\Components\Grid;


class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'itens';

    protected static ?string $title = 'Itens do Contrato';
    protected static ?string $modelLabel = 'Item';
    protected static ?string $pluralModelLabel = 'Itens';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_id')
                    ->label('Item')
                    ->options(Item::where('ativo', true)->pluck('nome', 'id'))
                    ->searchable()
                    ->required()
                    ->native(false)
                    ->columnSpanFull(),

                TextInput::make('quantidade_total')
                    ->label('Quantidade Total')
                    ->numeric()
                    ->minValue(0.001)
                    ->required()
                    ->columnSpan(1),

                TextInput::make('preco_unitario')
                    ->label('Preço Unitário')
                    ->numeric()
                    ->prefix('R$')
                    ->minValue(0.01)
                    ->required()
                    ->columnSpan(1),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Item')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('pivot.tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($state) => match ($state instanceof TipoItemContrato ? $state : TipoItemContrato::tryFrom($state)) {
                        TipoItemContrato::Compra       => 'primary',
                        TipoItemContrato::Aditivo      => 'success',
                        TipoItemContrato::Reequilibrio => 'warning',
                        default                        => 'gray',
                    })
                    ->formatStateUsing(function ($state) {
                        if ($state instanceof TipoItemContrato) {
                            return $state->label();
                        }
                        return TipoItemContrato::tryFrom($state)?->label() ?? $state;
                    }),

                TextColumn::make('pivot.quantidade_total')
                    ->label('Qtd. Total')
                    ->sortable(),

                TextColumn::make('pivot.quantidade_utilizada')
                    ->label('Qtd. Utilizada')
                    ->sortable(),

                TextColumn::make('pivot.preco_unitario')
                    ->label('Preço Unitário')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('pivot.preco_total')
                    ->label('Preço Total')
                    ->money('BRL')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make('compra')
                    ->label('Adicionar Item')
                    ->schema(fn() => [
                        $this->itemSelectCompra(),
                        Grid::make(2)->schema($this->quantidadePrecoSchema()),
                    ])
                    ->action(fn(array $data) => $this->attachItem($data, 'compra')),

                CreateAction::make('aditivo')
                    ->label('Aditivo')
                    ->color('success')
                    ->schema(fn() => [
                        $this->itemSelectAditivo(),
                        Grid::make(2)->schema($this->quantidadePrecoSchema()),
                    ])
                    ->action(fn(array $data) => $this->attachItem($data, 'aditivo')),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->defaultSort('nome')
            ->paginated([5, 10, 25]);
    }
    private function attachItem(array $data, string $tipo): void
    {
        $this->ownerRecord->itens()->attach($data['item_id'], [
            'tipo'                => $tipo,
            'quantidade_total'    => $data['quantidade_total'],
            'quantidade_utilizada' => 0,
            'preco_unitario'      => $data['preco_unitario'],
        ]);
    }

    private function itemSelectCompra(): Select
    {
        return Select::make('item_id')
            ->label('Item')
            ->options(
                Item::query()
                    ->where('ativo', true)
                    ->get()
                    ->groupBy(fn($item) => $item->tipo_item->value)
                    ->map(fn($group) => $group->mapWithKeys(fn($item) => [
                        $item->id => $item->nome_com_unidade,
                    ]))
                    ->toArray()
            )
            ->searchable();
    }

    private function itemSelectAditivo(): Select
    {
        return Select::make('item_id')
            ->label('Item')
            ->options(
                Item::query()
                    ->where('ativo', true)
                    ->get()
                    ->groupBy(fn($item) => $item->tipo_item->value)
                    ->map(fn($group) => $group->mapWithKeys(fn($item) => [
                        $item->id => $item->nome_com_unidade,
                    ]))
                    ->toArray()
            )
            ->searchable();
    }

    private function quantidadePrecoSchema(): array
    {
        return [
            TextInput::make('quantidade_total')
                ->label('Quantidade Total')
                ->numeric()
                ->minValue(0.001)
                ->required()
                ->columnSpan(1),

            TextInput::make('preco_unitario')
                ->label('Preço Unitário')
                ->numeric()
                ->prefix('R$')
                ->minValue(0.01)
                ->required()
                ->columnSpan(1),
        ];
    }


    public function isReadOnly(): bool
    {
        return false;
    }
}

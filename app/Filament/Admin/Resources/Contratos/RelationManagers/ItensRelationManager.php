<?php

namespace App\Filament\Admin\Resources\Contratos\RelationManagers;

use App\Models\Item;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use App\Models\Enums\TipoItemContrato;
use Filament\Schemas\Components\Grid;

class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'contratoItens';

    protected static ?string $title = 'Itens do Contrato';
    protected static ?string $modelLabel = 'Item';
    protected static ?string $pluralModelLabel = 'Itens';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->itemSelectCompra(),

                Grid::make(2)->schema($this->quantidadePrecoSchema()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // 🔥 agora vem da relação
                TextColumn::make('item.nome')
                    ->label('Item')
                    ->searchable(),

                TextColumn::make('tipo')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        TipoItemContrato::Compra       => 'primary',
                        TipoItemContrato::Aditivo      => 'success',
                        TipoItemContrato::Reequilibrio => 'warning',
                        default                        => 'gray',
                    })
                    ->formatStateUsing(fn($state) => $state?->label()),

                TextColumn::make('quantidade_total')
                    ->label('Qtd. Total')
                    ->sortable(),

                TextColumn::make('quantidade_utilizada')
                    ->label('Qtd. Utilizada')
                    ->sortable(),

                TextColumn::make('preco_unitario')
                    ->label('Preço Unitário')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('preco_total')
                    ->label('Preço Total')
                    ->money('BRL')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make('compra')
                    ->label('Adicionar Item')
                    ->schema([
                        $this->itemSelectCompra(),
                        Grid::make(2)->schema($this->quantidadePrecoSchema()),
                    ])
                    ->action(fn(array $data) => $this->attachItem($data, TipoItemContrato::Compra)),

                CreateAction::make('aditivo')
                    ->label('Aditivo')
                    ->color('success')
                    ->schema([
                        $this->itemSelectAditivo(),
                        Grid::make(2)->schema($this->quantidadePrecoSchema()),
                    ])
                    ->action(fn(array $data) => $this->attachItem($data, TipoItemContrato::Aditivo)),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25]);
    }

    private function attachItem(array $data, TipoItemContrato $tipo): void
    {
        $this->ownerRecord->contratoItens()->create([
            'item_id'              => $data['item_id'],
            'tipo'                 => $tipo,
            'quantidade_total'     => $data['quantidade_total'],
            'quantidade_utilizada' => 0,
            'preco_unitario'       => $data['preco_unitario'],
        ]);
    }

    private function itemSelectCompra(): Select
    {
        return Select::make('item_id')
            ->label('Item')
            ->options(fn() => $this->getGroupedOptions(
                Item::where('ativo', true)
            ))
            ->searchable()
            ->required()
            ->native(false)
            ->columnSpanFull();
    }

    private function itemSelectAditivo(): Select
    {
        return Select::make('item_id')
            ->label('Item (somente itens já existentes no contrato)')
            ->options(function () {
                $ids = $this->ownerRecord
                    ->contratoItens()
                    ->pluck('item_id');

                return $this->getGroupedOptions(
                    Item::whereIn('id', $ids)
                );
            })
            ->searchable()
            ->required()
            ->native(false)
            ->columnSpanFull();
    }

    private function getGroupedOptions($query)
    {
        return $query
            ->get()
            ->groupBy(fn($item) => $item->tipo_item->label())
            ->map(fn($group) => $group->mapWithKeys(fn($item) => [
                $item->id => "{$item->nome} - {$item->unidade_medida->value}",
            ]))
            ->toArray();
    }

    private function quantidadePrecoSchema(): array
    {
        return [
            TextInput::make('quantidade_total')
                ->label('Quantidade Total')
                ->numeric()
                ->minValue(0.001)
                ->required(),

            TextInput::make('preco_unitario')
                ->label('Preço Unitário')
                ->numeric()
                ->prefix('R$')
                ->minValue(0.01)
                ->required(),
        ];
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
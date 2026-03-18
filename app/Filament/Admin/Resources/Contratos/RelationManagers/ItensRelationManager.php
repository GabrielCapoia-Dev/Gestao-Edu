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
                Tables\Columns\TextColumn::make('nome')
                    ->label('Item')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('pivot.quantidade_total')
                    ->label('Qtd. Total')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pivot.quantidade_utilizada')
                    ->label('Qtd. Utilizada')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pivot.preco_unitario')
                    ->label('Preço Unitário')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pivot.preco_total')
                    ->label('Preço Total')
                    ->money('BRL')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar Item')
                    ->schema(fn() => [
                        Select::make('item_id')
                            ->label('Item')
                            ->options(Item::where('ativo', true)->pluck('nome', 'id'))
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
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
                    ])
                    ->action(function (array $data) {
                        $this->ownerRecord->itens()->attach($data['item_id'], [
                            'quantidade_total' => $data['quantidade_total'],
                            'quantidade_utilizada' => 0,
                            'preco_unitario' => $data['preco_unitario'],
                            'preco_total' => $data['quantidade_total'] * $data['preco_unitario'],
                        ]);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->action(function ($record, array $data) {
                        $record->pivot->update([
                            'quantidade_total' => $data['quantidade_total'],
                            'preco_unitario' => $data['preco_unitario'],
                            'preco_total' => $data['quantidade_total'] * $data['preco_unitario'],
                        ]);
                    }),
                DeleteAction::make(),
            ])
            ->defaultSort('nome')
            ->paginated([5, 10, 25]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}

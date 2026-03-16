<?php

namespace App\Filament\Admin\Resources\Contratos\RelationManagers;

use App\Models\Enums\UnidadeMedida;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'itens';

    protected static ?string $title = 'Itens do Contrato';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('quantidade')
                    ->label('Quantidade')
                    ->numeric()
                    ->minValue(0.001)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('unidade_medida')
                    ->label('Unidade')
                    ->formatStateUsing(fn ($state) => $state instanceof UnidadeMedida ? $state->label() : $state),

                TextColumn::make('pivot.quantidade')
                    ->label('Quantidade')
                    ->numeric(decimalPlaces: 3),

                TextColumn::make('pivot.quantidade_utilizada')
                    ->label('Utilizada')
                    ->numeric(decimalPlaces: 3),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        TextInput::make('quantidade')
                            ->label('Quantidade')
                            ->numeric()
                            ->minValue(0.001)
                            ->required(),
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
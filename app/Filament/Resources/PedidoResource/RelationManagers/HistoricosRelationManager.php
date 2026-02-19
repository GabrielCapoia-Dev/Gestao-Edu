<?php

namespace App\Filament\Resources\PedidoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoricosRelationManager extends RelationManager
{
    protected static string $relationship = 'historicos';
    protected static ?string $title = 'Histórico';
    protected static ?string $modelLabel = 'Registro';
    protected static ?string $pluralModelLabel = 'Histórico';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipoStatus.nome')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Responsável'),

                Tables\Columns\TextColumn::make('observacao')
                    ->label('Observação')
                    ->limit(100)
                    ->wrap(),

                Tables\Columns\IconColumn::make('notificacao_enviada')
                    ->label('Notificado')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25])
            ->striped();
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
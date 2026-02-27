<?php

namespace App\Filament\Resources\PedidoResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\User;

class HistoricosRelationManager extends RelationManager
{
    protected static string $relationship = 'historicos';

    protected static ?string $title = 'Histórico do Pedido';
    protected static ?string $modelLabel = 'Registro';
    protected static ?string $pluralModelLabel = 'Históricos';

    public function table(Table $table): Table
    {
        $latestId = $this->getOwnerRecord()
            ->historicos()
            ->latest()
            ->value('id');

        return $table
            ->columns([

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('statusNovo.nome')
                    ->label('Novo Status')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Responsável'),

                Tables\Columns\TextColumn::make('setor.nome')
                    ->label('Setor')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('descricao_alteracao')
                    ->label('Descrição Histórico')
                    ->limit(100)
                    ->wrap(),

            ])
            ->recordClasses(
                fn($record) => $record->id === $latestId ? 'highlight-latest' : null
            )
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25]);
    }


    public function isReadOnly(): bool
    {
        return true;
    }
}

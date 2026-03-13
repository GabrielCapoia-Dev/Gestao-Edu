<?php

namespace App\Filament\Admin\Resources\Pedidos\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ArquivosRelationManager extends RelationManager
{
    protected static string $relationship = 'arquivos_sem_fotos_problema';

    protected static ?string $title = 'Arquivos';
    protected static ?string $modelLabel = 'Arquivo';
    protected static ?string $pluralModelLabel = 'Arquivos';

    public function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('tipo_arquivo')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nome_original')
                    ->label('Arquivo')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn($record) => route('pedidos.arquivos.download', $record))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Enviado por')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
<?php

namespace App\Filament\Resources\PedidoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ArquivosRelationManager extends RelationManager
{
    protected static string $relationship = 'fotos';
    protected static ?string $title = 'Fotos';
    protected static ?string $modelLabel = 'Foto';
    protected static ?string $pluralModelLabel = 'Fotos';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('caminho')
                ->label('Foto')
                ->image()
                ->required()
                ->directory('pedidos/fotos')
                ->maxSize(5120),

            Forms\Components\TextInput::make('descricao')
                ->label('Descrição')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('caminho')
                    ->label('Foto')
                    ->disk('public')
                    ->height(80)
                    ->width(80),

                Tables\Columns\TextColumn::make('nome_original')
                    ->label('Nome'),

                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->limit(50),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Adicionada em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
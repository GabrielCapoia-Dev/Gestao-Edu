<?php

namespace App\Filament\Resources\PedidoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Layout\Stack;
use Illuminate\Support\Facades\Storage;

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
                Stack::make([
                    Tables\Columns\TextColumn::make('caminho')
                        ->label('Foto')
                        ->html()
                        ->formatStateUsing(
                            fn(string $state): string =>
                            '<img 
                            src="' . Storage::url($state) . '"
                            style="
                                width: 100%;
                                height: auto;
                                max-height: 400px;
                                object-fit: contain;
                                border-radius: 0.5rem;
                                background: #f3f4f6;
                            "
                        />'
                        ),
                ]),
            ])
            ->contentGrid([
                'default' => 1,
                'sm'      => 2,
                'md'      => 3,
                'xl'      => 4,
            ])
            ->paginated([12, 24, 48]);
    }
}

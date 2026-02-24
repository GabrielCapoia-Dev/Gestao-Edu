<?php

namespace App\Filament\Resources\PedidoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use App\Models\Enums\TipoArquivoPedido;

class ArquivosRelationManager extends RelationManager
{
    protected static string $relationship = 'arquivos_sem_fotos_problema';

    protected static ?string $title = 'Arquivos';
    protected static ?string $modelLabel = 'Arquivo';
    protected static ?string $pluralModelLabel = 'Arquivos';

    public function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Select::make('tipo_arquivo')
                ->label('Tipo do Arquivo')
                ->options(
                    collect(TipoArquivoPedido::cases())
                        ->reject(fn($case) => $case === TipoArquivoPedido::FOTOS_PROBLEMA)
                        ->mapWithKeys(fn($case) => [
                            $case->value => $case->label(),
                        ])
                        ->toArray()
                )
                ->required()
                ->native(false),

            Forms\Components\FileUpload::make('caminho')
                ->label('Arquivo')
                ->disk('public')
                ->directory('pedidos')
                ->visibility('public')
                ->storeFiles()
                ->preserveFilenames()
                ->required(),

            Forms\Components\Textarea::make('descricao')
                ->label('Descrição')
                ->maxLength(1000)
                ->rows(2),

        ]);
    }

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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}

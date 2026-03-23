<?php

namespace App\Filament\Admin\Resources\Pedidos\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Schema;
use App\Models\Enums\TipoArquivoPedido;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;

class ArquivosRelationManager extends RelationManager
{
    protected static string $relationship = 'arquivos_sem_fotos_problema';

    protected static ?string $title = 'Arquivos';
    protected static ?string $modelLabel = 'Arquivo';
    protected static ?string $pluralModelLabel = 'Arquivos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('tipo_arquivo')
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
                    ->columnSpanFull()
                    ->native(false),

                FileUpload::make('caminho')
                    ->label('Arquivo')
                    ->disk('public')
                    ->directory('pedidos')
                    ->visibility('public')
                    ->storeFiles()
                    ->multiple(false)
                    ->columnSpanFull()
                    ->preserveFilenames()
                    ->required(),

                Textarea::make('descricao')
                    ->label('Descrição')
                    ->maxLength(1000)
                    ->columnSpanFull()
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
            ->headerActions([
                CreateAction::make()
                    ->label('Enviar Arquivo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),

            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}

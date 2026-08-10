<?php

namespace App\Filament\Admin\Resources\Pedidos\RelationManagers;

use App\Filament\Admin\Components\PedidoArquivoUpload;
use App\Models\Enums\TipoArquivoPedido;
use App\Support\PedidoImageUpload;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

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
                            ->reject(fn ($case) => $case === TipoArquivoPedido::FOTOS_PROBLEMA)
                            ->mapWithKeys(fn ($case) => [
                                $case->value => $case->label(),
                            ])
                            ->toArray()
                    )
                    ->required()
                    ->live()
                    ->columnSpanFull()
                    ->native(false),

                PedidoArquivoUpload::make('caminho')
                    ->label('Arquivo')
                    ->aceitarSomenteImagensQuando(
                        fn (Get $get): bool => TipoArquivoPedido::tryFrom((string) $get('tipo_arquivo'))?->exigeImagem() ?? false
                    )
                    ->validationMessages([
                        'mimetypes' => fn (Get $get): string => TipoArquivoPedido::tryFrom((string) $get('tipo_arquivo'))?->exigeImagem()
                            ? PedidoImageUpload::MESSAGE
                            : 'Envie um arquivo em um formato permitido.',
                    ])
                    ->helperText(fn (Get $get): ?string => TipoArquivoPedido::tryFrom((string) $get('tipo_arquivo'))?->exigeImagem()
                        ? PedidoImageUpload::MESSAGE
                        : null)
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
                    ->url(fn ($record) => route('pedidos.arquivos.download', $record))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('usuario_exibicao')
                    ->label('Enviado por')
                    ->state(fn ($record): string => $record->usuarioNomeExibicao()),

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
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}

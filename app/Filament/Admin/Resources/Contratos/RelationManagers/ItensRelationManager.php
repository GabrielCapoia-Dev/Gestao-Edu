<?php

namespace App\Filament\Admin\Resources\Contratos\RelationManagers;

use App\Models\Item;
use App\Services\Contratos\ContratoItemSpreadsheetService;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use App\Models\Enums\TipoItemContrato;
use Filament\Schemas\Components\Grid;
use InvalidArgumentException;

class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'contratoItens';

    protected static ?string $title = 'Itens do Contrato';
    protected static ?string $modelLabel = 'Item';
    protected static ?string $pluralModelLabel = 'Itens';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->itemSelectCompra(),

                Grid::make(2)->schema($this->quantidadePrecoSchema()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.nome')
                    ->label('Item')
                    ->searchable(),

                TextColumn::make('item.codigo')
                    ->label('Código')
                    ->searchable(),

                TextColumn::make('tipo')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        TipoItemContrato::Compra       => 'primary',
                        TipoItemContrato::Aditivo      => 'success',
                        TipoItemContrato::Reequilibrio => 'warning',
                        default                        => 'gray',
                    })
                    ->formatStateUsing(fn($state) => $state?->label()),

                TextColumn::make('quantidade_total')
                    ->label('Qtd. Total')
                    ->sortable(),

                TextColumn::make('quantidade_utilizada')
                    ->label('Qtd. Utilizada')
                    ->sortable(),

                TextColumn::make('quantidade_reservada')
                    ->label('Qtd. Reservada')
                    ->sortable()
                    ->color('warning'),

                TextColumn::make('saldo_disponivel')
                    ->label('Qtd. Restante')
                    ->getStateUsing(fn($record) => $record->saldo_disponivel)
                    ->color(fn($record) => $record->saldo_disponivel <= 0 ? 'danger' : 'success')
                    ->sortable(false),

                TextColumn::make('preco_unitario')
                    ->label('Preço Unitário')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('preco_total')
                    ->label('Preço Total')
                    ->money('BRL')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('exportarModelo')
                    ->label('Exportar Modelo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => $this->spreadsheetService()->exportarModelo()),

                Action::make('importarTabela')
                    ->label('Importar Tabela')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->schema([
                        FileUpload::make('arquivo')
                            ->label('Arquivo da planilha')
                            ->disk('local')
                            ->directory('imports/contratos-itens')
                            ->visibility('private')
                            ->storeFiles()
                            ->preserveFilenames()
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/csv',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->maxSize(5120)
                            ->required()
                            ->helperText('Aceita arquivos CSV e XLSX com as colunas Código do item, Quantidade e Preço unitário.'),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $resultado = $this->spreadsheetService()->importar(
                                $this->ownerRecord,
                                $data['arquivo']
                            );

                            Notification::make()
                                ->title('Importação concluída')
                                ->body("{$resultado['total_importado']} item(ns) foram adicionados ao contrato.")
                                ->success()
                                ->send();
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()
                                ->title('Não foi possível importar a planilha')
                                ->body($exception->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                CreateAction::make('compra')
                    ->label('Adicionar Item')
                    ->schema([
                        $this->itemSelectCompra(),
                        Grid::make(2)->schema($this->quantidadePrecoSchema()),
                    ])
                    ->action(fn(array $data) => $this->attachItem($data, TipoItemContrato::Compra)),

                CreateAction::make('aditivo')
                    ->label('Aditivo')
                    ->color('success')
                    ->schema([
                        $this->itemSelectAditivo(),

                        Grid::make(2)->schema([
                            TextInput::make('quantidade_total')
                                ->label('Quantidade Total')
                                ->numeric()
                                ->minValue(0.001)
                                ->required(),

                            TextInput::make('preco_unitario')
                                ->label('Preço Unitário')
                                ->numeric()
                                ->prefix('R$')
                                ->disabled()
                                ->dehydrated()
                                ->required()
                                ->afterStateHydrated(function ($set, $get) {
                                    $itemId = $get('item_id');

                                    if (!$itemId) return;

                                    $preco = $this->getPrecoCompra($itemId);

                                    $set('preco_unitario', $preco);
                                })
                                ->reactive(),
                        ]),
                    ])
                    ->action(fn(array $data) => $this->attachItem($data, TipoItemContrato::Aditivo)),
            ])
            ->recordActions([
                EditAction::make('reequilibrio')
                    ->label('Reequilíbrio')
                    ->color('warning')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        // TODO: adicionar campos de reequilíbrio
                    ])
                    ->action(fn(array $data, $record) => null),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5);
    }

    private function attachItem(array $data, TipoItemContrato $tipo): void
    {
        if ($tipo === TipoItemContrato::Aditivo) {
            $data['preco_unitario'] = $this->getPrecoCompra($data['item_id']);
        }

        $this->ownerRecord->contratoItens()->create([
            'item_id'              => $data['item_id'],
            'tipo'                 => $tipo,
            'quantidade_total'     => $data['quantidade_total'],
            'quantidade_utilizada' => 0,
            'preco_unitario'       => $data['preco_unitario'],
        ]);
    }

    private function itemSelectCompra(): Select
    {
        return Select::make('item_id')
            ->label('Item')
            ->options(fn() => $this->getGroupedOptions(
                Item::where('ativo', true)
            ))
            ->searchable()
            ->required()
            ->native(false)
            ->columnSpanFull();
    }

    private function itemSelectAditivo(): Select
    {
        return Select::make('item_id')
            ->label('Item (somente itens já existentes no contrato)')
            ->options(function () {
                $ids = $this->ownerRecord
                    ->contratoItens()
                    ->pluck('item_id');

                return $this->getGroupedOptions(
                    Item::whereIn('id', $ids)
                );
            })
            ->afterStateUpdated(function ($state, $set) {
                $preco = $this->getPrecoCompra($state);
                $set('preco_unitario', $preco);
            })
            ->reactive()
            ->searchable()
            ->required()
            ->native(false)
            ->columnSpanFull();
    }

    private function getPrecoCompra($itemId): ?float
    {
        return $this->ownerRecord
            ->contratoItens()
            ->where('item_id', $itemId)
            ->where('tipo', TipoItemContrato::Compra)
            ->value('preco_unitario');
    }

    private function getGroupedOptions($query)
    {
        return $query
            ->get()
            ->groupBy(fn($item) => $item->tipo_item->label())
            ->map(fn($group) => $group->mapWithKeys(fn($item) => [
                $item->id => "{$item->nome} - {$item->unidade_medida->value}",
            ]))
            ->toArray();
    }

    private function quantidadePrecoSchema(): array
    {
        return [
            TextInput::make('quantidade_total')
                ->label('Quantidade Total')
                ->numeric()
                ->minValue(0.001)
                ->required(),

            TextInput::make('preco_unitario')
                ->label('Preço Unitário')
                ->numeric()
                ->prefix('R$')
                ->minValue(0.01)
                ->required(),
        ];
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function spreadsheetService(): ContratoItemSpreadsheetService
    {
        return app(ContratoItemSpreadsheetService::class);
    }
}

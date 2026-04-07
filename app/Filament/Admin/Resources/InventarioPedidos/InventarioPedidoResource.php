<?php

namespace App\Filament\Admin\Resources\InventarioPedidos;

use App\Filament\Admin\Resources\InventarioPedidos\Pages\CreateInventarioPedido;
use App\Filament\Admin\Resources\InventarioPedidos\Pages\ListInventarioPedidos;
use App\Filament\Admin\Resources\InventarioPedidos\Pages\ViewInventarioPedido;
use App\Filament\Admin\Resources\InventarioPedidos\Tables\InventarioPedidosTable;
use App\Models\InventarioPedido;
use App\Models\Item;
use App\Services\Inventario\InventarioContextService;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class InventarioPedidoResource extends Resource
{
    protected static ?string $model = InventarioPedido::class;

    protected static ?string $modelLabel = 'Pedido de Inventário';

    protected static ?string $pluralModelLabel = 'Pedidos de Inventário';

    protected static ?string $slug = 'pedidos-inventario';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';

    protected static ?int $navigationSort = 12;

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasPermissionTo('Listar Pedidos de Inventário') ?? false;
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();

        if (! $user?->hasPermissionTo('Criar Pedidos de Inventário')) {
            return false;
        }

        return ! app(InventarioContextService::class)->ehGestorGeral($user);
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pedido para a Matriz')
                    ->schema([
                        Textarea::make('observacao_escola')
                            ->label('Observações gerais da escola')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Repeater::make('itens')
                            ->label('Itens solicitados')
                            ->schema([
                                Select::make('item_id')
                                    ->label('Item')
                                    ->options(fn (): array => Item::query()
                                        ->where('ativo', true)
                                        ->orderBy('nome')
                                        ->get()
                                        ->mapWithKeys(fn (Item $item): array => [
                                            $item->getKey() => $item->nome . ' - ' . $item->unidade_medida->value,
                                        ])
                                        ->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('quantidade_solicitada')
                                    ->label('Quantidade solicitada')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.001)
                                    ->step('0.001'),
                                Textarea::make('observacao_solicitacao')
                                    ->label('Observação do item')
                                    ->rows(2)
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return InventarioPedidosTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumo do Pedido')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Pedido')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->label() ?? $state)
                            ->color(fn ($state) => $state?->color() ?? 'gray'),
                        TextEntry::make('escola.nome')
                            ->label('Escola')
                            ->placeholder('-'),
                        TextEntry::make('inventario.nome')
                            ->label('Inventário')
                            ->placeholder('-'),
                        TextEntry::make('solicitadoPor.name')
                            ->label('Solicitado por')
                            ->placeholder('-'),
                        TextEntry::make('romaneio.codigo')
                            ->label('Romaneio')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('entregue_em')
                            ->label('Entregue em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                    ])
                    ->columns(4),

                Section::make('Observações')
                    ->schema([
                        TextEntry::make('observacao_escola')
                            ->label('Escola')
                            ->placeholder('Sem observação da escola.'),
                        TextEntry::make('observacao_gestor')
                            ->label('Gestor Geral')
                            ->placeholder('Sem observação do gestor.'),
                        TextEntry::make('observacao_conferencia')
                            ->label('Conferência')
                            ->placeholder('Sem observação de conferência.'),
                    ])
                    ->columns(1),

                Section::make('Itens do Pedido')
                    ->schema([
                        RepeatableEntry::make('itens')
                            ->label('')
                            ->table([
                                TableColumn::make('Item'),
                                TableColumn::make('Solicitada'),
                                TableColumn::make('Aprovada'),
                                TableColumn::make('Recebida'),
                                TableColumn::make('Status'),
                                TableColumn::make('Observações'),
                            ])
                            ->schema([
                                TextEntry::make('item.nome')
                                    ->label('Item'),
                                TextEntry::make('quantidade_solicitada')
                                    ->label('Solicitada')
                                    ->numeric(decimalPlaces: 3),
                                TextEntry::make('quantidade_aprovada')
                                    ->label('Aprovada')
                                    ->numeric(decimalPlaces: 3)
                                    ->placeholder('-'),
                                TextEntry::make('quantidade_recebida')
                                    ->label('Recebida')
                                    ->numeric(decimalPlaces: 3)
                                    ->placeholder('-'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn ($state) => $state?->label() ?? $state)
                                    ->badge()
                                    ->color(fn ($state) => match ((string) ($state?->value ?? $state)) {
                                        'aprovado', 'conferido' => 'success',
                                        'recusado' => 'danger',
                                        default => 'warning',
                                    }),
                                TextEntry::make('observacoes_combinadas')
                                    ->label('Observações')
                                    ->state(fn ($record): string => collect([
                                        $record->observacao_solicitacao,
                                        $record->observacao_aprovacao,
                                        $record->observacao_conferencia,
                                    ])->filter()->implode(' | ') ?: '-'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventarioPedidos::route('/'),
            'create' => CreateInventarioPedido::route('/create'),
            'view' => ViewInventarioPedido::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return app(\App\Services\Inventario\InventarioPedidoService::class)
            ->queryTabela(Auth::user());
    }
}

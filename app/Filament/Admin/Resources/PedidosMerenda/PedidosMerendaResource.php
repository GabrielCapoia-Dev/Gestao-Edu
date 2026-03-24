<?php

namespace App\Filament\Admin\Resources\PedidosMerenda;

use App\Filament\Admin\Resources\PedidosMerenda\Pages\CreatePedidoMerenda;
use App\Filament\Admin\Resources\PedidosMerenda\Pages\ListPedidosMerenda;
use App\Models\PedidoMerenda;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;
use App\Filament\Admin\Resources\PedidosMerenda\Tables\PedidoMerendaTable;

class PedidosMerendaResource extends Resource
{
    protected static ?string $model = PedidoMerenda::class;
    protected static ?string $modelLabel = 'Pedido';
    protected static ?string $pluralModelLabel = 'Pedidos';
    protected static ?string $slug = 'pedidos-merenda';
    protected static ?string $navigationParentItem = 'Gestão de Estoque';
    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingCart;
    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return PedidoMerendaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPedidosMerenda::route('/'),
            'create' => CreatePedidoMerenda::route('/create'),
        ];
    }
}
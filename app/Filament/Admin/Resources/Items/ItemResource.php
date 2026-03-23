<?php

namespace App\Filament\Admin\Resources\Items;

use App\Filament\Admin\Resources\Items\Pages\ManageItems;
use App\Models\Item;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Services\ItemService;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = 'fluentui-food-apple-24';
    protected static ?string $navigationParentItem = 'Pedidos';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';
    protected static ?string $navigationLabel = 'Itens';
    protected static ?string $pluralLabel = 'Itens';
    public static ?string $modelLabel = 'Item';
    protected static ?string $slug = 'itens';

    public static function service(): ItemService
    {
        return app(ItemService::class);
    }

    public static function form(Schema $schema): Schema
    {
        return self::service()->configurarFormulario($schema);
    }

    public static function table(Table $table): Table
    {
        return self::service()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageItems::route('/'),
        ];
    }
}
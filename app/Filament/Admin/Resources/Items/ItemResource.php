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

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nome';

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

<?php

namespace App\Filament\Admin\Resources\Estoques;

use App\Models\Estoque;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class EstoqueResource extends Resource
{
    protected static ?string $model = Estoque::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingStorefront;
    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';
    protected static ?string $navigationLabel = 'Estoque';
    protected static ?string $modelLabel = 'Estoque';
    protected static ?string $pluralModelLabel = 'Estoque';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?int $navigationSort = 4;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEstoque::route('/'),
        ];
    }
}
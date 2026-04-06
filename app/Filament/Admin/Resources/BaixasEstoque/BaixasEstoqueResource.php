<?php

namespace App\Filament\Admin\Resources\BaixasEstoque;

use App\Filament\Admin\Resources\BaixasEstoque\Pages\ListBaixasEstoque;
use App\Filament\Admin\Resources\BaixasEstoque\Tables\BaixasEstoqueTable;
use App\Models\Estoque;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BaixasEstoqueResource extends Resource
{
    protected static ?string $model = Estoque::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowTrendingDown;
    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';
    protected static ?string $navigationParentItem = 'Gestão de Estoque';
    protected static ?string $navigationLabel = 'Baixas';
    protected static ?string $pluralModelLabel = 'Baixas de Estoque';
    protected static ?string $modelLabel = 'Baixa de Estoque';
    protected static ?string $slug = 'baixas-estoque';
    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasPermissionTo('Listar Gestão de Estoque') ?? false;
    }

    public static function table(Table $table): Table
    {
        return BaixasEstoqueTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBaixasEstoque::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\BaixasInventario;

use App\Filament\Admin\Resources\BaixasInventario\Pages\ListBaixasInventario;
use App\Filament\Admin\Resources\BaixasInventario\Tables\BaixasInventarioTable;
use App\Models\InventarioEstoque;
use App\Services\Inventario\InventarioContextService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BaixasInventarioResource extends Resource
{
    protected static ?string $model = InventarioEstoque::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';

    protected static ?string $navigationLabel = 'Baixas do Inventário';

    protected static ?string $pluralModelLabel = 'Baixas do Inventário';

    protected static ?string $modelLabel = 'Baixa de Inventário';

    protected static ?string $slug = 'baixas-inventario';

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return ($user?->hasPermissionTo('Listar Gestão de Inventário') ?? false)
            && static::resolverInventarioId() !== null;
    }

    public static function table(Table $table): Table
    {
        return BaixasInventarioTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBaixasInventario::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $inventarioId = static::resolverInventarioId();

        return parent::getEloquentQuery()
            ->with(['item', 'inventario.escola'])
            ->when($inventarioId, fn (Builder $query) => $query->where('inventario_id', $inventarioId))
            ->when(! $inventarioId, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public static function resolverInventarioId(): ?int
    {
        $inventario = app(InventarioContextService::class)->resolverInventario(Auth::user(), request()->integer('inventario'));

        return $inventario?->getKey();
    }
}

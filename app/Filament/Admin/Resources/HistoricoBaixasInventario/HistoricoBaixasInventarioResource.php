<?php

namespace App\Filament\Admin\Resources\HistoricoBaixasInventario;

use App\Filament\Admin\Resources\HistoricoBaixasInventario\Pages\ListHistoricoBaixasInventario;
use App\Filament\Admin\Resources\HistoricoBaixasInventario\Tables\HistoricoBaixasInventarioTable;
use App\Models\InventarioBaixa;
use App\Services\Inventario\InventarioContextService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Inventario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class HistoricoBaixasInventarioResource extends Resource
{
    protected static ?string $model = InventarioBaixa::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';

    protected static ?string $navigationLabel = 'Histórico de Baixas';

    protected static ?string $pluralModelLabel = 'Histórico de Baixas';

    protected static ?string $modelLabel = 'Baixa';

    protected static ?string $slug = 'historico-baixas-inventario';

    public static function canViewAny(): bool
    {
        return Gate::allows('accessBaixasWithContext', Inventario::class);
    }

    public static function table(Table $table): Table
    {
        return HistoricoBaixasInventarioTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHistoricoBaixasInventario::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $inventarioId = static::resolverInventarioId();

        return parent::getEloquentQuery()
            ->with(['estoque.item', 'estoque.inventario.escola'])
            ->when($inventarioId, fn (Builder $query) => $query->whereHas('estoque', fn (Builder $estoqueQuery) => $estoqueQuery->where('inventario_id', $inventarioId)))
            ->when(! $inventarioId, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    protected static function resolverInventarioId(): ?int
    {
        $inventario = app(InventarioContextService::class)->resolverInventario(Auth::user(), request()->integer('inventario'));

        return $inventario?->getKey();
    }
}

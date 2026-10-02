<?php

namespace App\Filament\Admin\Resources\SaldosEleitorais;

use App\Filament\Admin\Resources\SaldosEleitorais\Pages\ListSaldosEleitorais;
use App\Models\SaldoEleitoral;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SaldoEleitoralResource extends Resource
{
    protected static ?string $model = SaldoEleitoral::class;

    protected static ?string $slug = 'saldo-eleitoral';

    protected static ?string $navigationLabel = 'Saldo Eleitoral';

    protected static ?string $navigationParentItem = 'Servidores';

    protected static ?string $pluralModelLabel = 'Saldo Eleitoral';

    protected static ?string $modelLabel = 'Movimentação de saldo';

    protected static string|UnitEnum|null $navigationGroup = 'Acesso';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermissionTo('Gerenciar Saldo Eleitoral', 'web') ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getPages(): array
    {
        return ['index' => ListSaldosEleitorais::route('/')];
    }
}

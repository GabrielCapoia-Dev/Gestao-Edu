<?php

namespace App\Filament\Admin\Resources\ReservasVeiculos;

use App\Filament\Admin\Resources\ReservasVeiculos\Pages\ManageReservasVeiculos;
use App\Filament\Admin\Resources\ReservasVeiculos\Tables\ReservasVeiculosTable;
use App\Models\ReservaVeiculo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReservaVeiculoResource extends Resource
{
    protected static ?string $model = ReservaVeiculo::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Reserva de veículos';

    protected static ?string $modelLabel = 'Reserva de veículo';

    protected static ?string $pluralModelLabel = 'Reservas de veículos';

    protected static ?string $slug = 'reservas-veiculos';

    public static function table(Table $table): Table
    {
        return ReservasVeiculosTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'veiculo:id,placa,identificacao,ativo',
            'usuario:id,name,email',
            'escola:id,nome',
            'canceladoPor:id,name',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReservasVeiculos::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Escolas;

use App\Filament\Admin\Resources\Escolas\Pages\ManageEscolas;
use App\Filament\Admin\Resources\Escolas\Pages\ManageLotacoes;
use App\Models\LocalTrabalho;
use App\Services\EscolaService as Service;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class EscolaResource extends Resource
{
    protected static ?string $model = LocalTrabalho::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingLibrary;

    protected static ?string $recordTitleAttribute = 'nome';

    public static ?string $modelLabel = 'Local de trabalho';
    public static ?string $pluralModelLabel = 'Locais de trabalho';
    public static ?string $slug = 'escolas';

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';


    protected static function service(): Service
    {
        return app(Service::class);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $policy = Gate::getPolicyFor(static::getModel());

        if ($user && $policy && method_exists($policy, 'applyViewAnyScope')) {
            return $policy->applyViewAnyScope($user, $query);
        }

        return $query->whereRaw('1 = 0');
    }


    public static function form(Schema $schema): Schema
    {
        return static::service()->configurarFormulario($schema);
    }

    public static function table(Table $table): Table
    {
        return static::service()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEscolas::route('/'),
            'lotacoes' => ManageLotacoes::route('/{record}/lotacoes'),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Escolas;

use App\Filament\Admin\Resources\Escolas\Pages\ManageEscolas;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use App\Models\Escola;
use App\Services\EscolaService as Service;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;


class EscolaResource extends Resource
{
    protected static ?string $model = Escola::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingLibrary;

    protected static ?string $recordTitleAttribute = 'nome';

    public static ?string $modelLabel = 'Escola';
    public static ?string $pluralModelLabel = 'Escolas';
    public static ?string $slug = 'escolas';

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';


    protected static function service(): Service
    {
        return app(Service::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('ativo', true);
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
        ];
    }
}

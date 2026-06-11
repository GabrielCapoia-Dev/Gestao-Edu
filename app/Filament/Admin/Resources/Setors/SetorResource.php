<?php

namespace App\Filament\Admin\Resources\Setors;

use App\Filament\Admin\Resources\Setors\Pages\ManageSetors;
use App\Models\Setor;
use App\Services\SetorService;
use App\Services\UserSetorAccessService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SetorResource extends Resource
{
    protected static ?string $model = Setor::class;

    protected static ?string $pluralModelLabel = 'Setores';

    protected static ?string $modelLabel = 'Setor';

    protected static ?string $navigationLabel = 'Gestao de Setores';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice;
    
    protected static string|UnitEnum|null $navigationGroup = 'Manutenção';

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
            'index' => ManageSetors::route('/'),
        ];
    }

    protected static function service(): SetorService
    {
        return app(SetorService::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return app(UserSetorAccessService::class)
            ->applySetorScope(
                parent::getEloquentQuery()
                    ->where('ativo', true)
                    ->with('acessosConcedidos'),
                Auth::user(),
                'id',
            );
    }
}

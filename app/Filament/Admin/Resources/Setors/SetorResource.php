<?php

namespace App\Filament\Admin\Resources\Setors;

use App\Filament\Admin\Resources\Setors\Pages\ManageSetors;
use App\Models\Setor;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Resources\SetorResource\Pages;
use App\Filament\Resources\SetorResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Services\SetorService;
use Illuminate\Support\Facades\Auth;
use UnitEnum;


class SetorResource extends Resource
{
    protected static ?string $model = Setor::class;
    protected static ?string $pluralModelLabel = 'Setores';
    protected static ?string $modelLabel = 'Setor';
    protected static bool $shouldRegisterNavigation = false;
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
        return parent::getEloquentQuery()
            ->where('ativo', true);
    }
}

<?php

namespace App\Filament\Admin\Resources\TipoManutencaos;

use App\Filament\Admin\Resources\TipoManutencaos\Pages\ManageTipoManutencaos;
use App\Models\TipoManutencao;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Resources\TipoManutencaoResource\Pages;
use App\Filament\Resources\TipoManutencaoResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class TipoManutencaoResource extends Resource
{
    // protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    // protected static ?string $navigationGroup = 'Manutenção';
    protected static ?string $navigationLabel = 'Tipo de Manutenção';
    protected static ?string $pluralModelLabel = 'Tipos de Manutenções';
    protected static ?string $modelLabel = 'Tipo de Manutenção';
    protected static ?string $model = TipoManutencao::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::WrenchScrewdriver;
    protected static string|UnitEnum|null $navigationGroup = 'Manutenção';
    protected static ?string $recordTitleAttribute = 'nome';

    

    public static function form(Schema $schema): Schema
    {
        return static::tipoManutencaoService()->configurarFormulario($schema);
    }

    public static function table(Table $table): Table
    {
        return static::tipoManutencaoService()->configurarTabela($table, User::authUser());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTipoManutencaos::route('/'),
        ];
    }

    protected static function tipoManutencaoService(): \App\Services\TipoManutencaoService
    {
        return app(\App\Services\TipoManutencaoService::class);
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

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['alterado_por'] = User::authUser()?->name;
        return $data;
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoManutencaoResource\Pages;
use App\Filament\Resources\TipoManutencaoResource\RelationManagers;
use App\Models\TipoManutencao;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use function Symfony\Component\String\s;

class TipoManutencaoResource extends Resource
{
    protected static ?string $model = TipoManutencao::class;


    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Tipo de Manutenção';
    protected static ?string $pluralModelLabel = 'Tipos de Manutenções';
    protected static ?string $modelLabel = 'Tipo de Manutenção';

    protected static ?string $navigationGroup = 'Manutenção';
    protected static function tipoManutencaoService(): \App\Services\TipoManutencaoService
    {
        return app(\App\Services\TipoManutencaoService::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('ativo', true);
    }

    public static function form(Form $form): Form
    {
        return static::tipoManutencaoService()->configurarFormulario($form);
    }

    public static function table(Table $table): Table
    {
        return static::tipoManutencaoService()->configurarTabela($table, User::authUser());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTipoManutencaos::route('/'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['alterado_por'] = User::authUser()?->name;
        return $data;
    }
}

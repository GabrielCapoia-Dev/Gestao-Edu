<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SetorResource\Pages;
use App\Filament\Resources\SetorResource\RelationManagers;
use App\Models\Setor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Services\SetorService;
use Illuminate\Support\Facades\Auth;

class SetorResource extends Resource
{
    protected static ?string $model = Setor::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Manutenção';
    protected static ?string $pluralModelLabel = 'Setores';
    protected static ?string $modelLabel = 'Setor';
    protected static bool $shouldRegisterNavigation = false;

    protected static function service(): SetorService
    {
        return app(SetorService::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('ativo', true);
    }

    public static function form(Form $form): Form
    {
        return static::service()->configurarFormulario($form);
    }

    public static function table(Table $table): Table
    {
        return static::service()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSetors::route('/'),
        ];
    }
}

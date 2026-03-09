<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoriaResource\Pages;
use App\Filament\Resources\CategoriaResource\RelationManagers;
use App\Models\Categoria;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use App\Services\CategoriaService as Service;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = "Alimentação Escolar";

    public static ?string $label = 'Categoria';

    public static ?string $pluralLabel = 'Categorias';


    protected static function service(): Service
    {
        return app(Service::class);
    }


    public static function form(Form $form): Form
    {
        return app(Service::class)->configurarFormulario($form, Auth::user());
    }

    public static function table(Table $table): Table
    {
        return app(Service::class)->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCategorias::route('/'),
        ];
    }
}

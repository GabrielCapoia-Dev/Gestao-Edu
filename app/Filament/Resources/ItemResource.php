<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource\RelationManagers;
use App\Models\Item;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Services\ItemService as Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = "Alimentação Escolar";
    public static ?string $label = 'Item';
    public static ?string $pluralLabel = 'Itens';
    protected static ?string $slug = 'itens';

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
            'index' => Pages\ManageItems::route('/'),
        ];
    }
}

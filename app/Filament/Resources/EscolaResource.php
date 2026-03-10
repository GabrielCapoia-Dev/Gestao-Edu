<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EscolaResource\Pages;
use App\Models\Escola;
use App\Services\EscolaService as Service;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EscolaResource extends Resource
{
    protected static ?string $model = Escola::class;

    // protected static ?string $navigationGroup = "Gerenciamento Escolar";
    // protected static ?string $navigationIcon = 'heroicon-o-building-library';
    public static ?string $modelLabel = 'Escola';
    public static ?string $pluralModelLabel = 'Escolas';
    public static ?string $slug = 'escolas';

    protected static function service(): Service
    {
        return app(Service::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('ativo', true);
    }

    // public static function form(Form $form): Form
    // {
    //     return static::service()->configurarFormulario($form);
    // }


    public static function table(Table $table): Table
    {
        return static::service()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEscolas::route('/'),
        ];
    }
}

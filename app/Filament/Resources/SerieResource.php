<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SerieResource\Pages;
use App\Models\Serie;
use App\Services\SerieService as Service;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SerieResource extends Resource
{
    protected static ?string $model = Serie::class;

    // protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    // protected static ?string $navigationGroup = 'Gerenciamento Escolar';

    protected static ?string $navigationLabel = 'Series';

    protected static ?string $pluralModelLabel = 'Series';

    protected static ?string $modelLabel = 'Serie';


    public static function service(): Service
    {
        return app(Service::class);
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
            'index' => Pages\ManageSeries::route('/'),
        ];
    }
}

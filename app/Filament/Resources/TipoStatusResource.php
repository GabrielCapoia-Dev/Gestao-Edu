<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoStatusResource\Pages;
use App\Models\TipoStatus;
use App\Services\TipoStatusService as Service;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class TipoStatusResource extends Resource
{
    protected static ?string $model = TipoStatus::class;
    // protected static ?string $navigationGroup = 'Manutenção';
    // protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $modelLabel = 'Status';
    protected static ?string $pluralModelLabel = 'Status';
    protected static ?string $slug = 'tipo-status';
    protected static ?int $navigationSort = 3;
    protected static bool $shouldRegisterNavigation = false;

    // public static function form(Form $form): Form
    // {
    //     return app(Service::class)->configurarFormulario($form);
    // }

    public static function table(Table $table): Table
    {
        return app(Service::class)->configurarTabela($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTipoStatuses::route('/'),
        ];
    }
}

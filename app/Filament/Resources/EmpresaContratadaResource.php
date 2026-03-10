<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmpresaContratadaResource\Pages;
use App\Models\EmpresaContratada;
use App\Services\EmpresaContratadaService as Service;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class EmpresaContratadaResource extends Resource
{
    protected static ?string $model = EmpresaContratada::class;
    // protected static ?string $navigationGroup = 'Manutenção';
    // protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $modelLabel = 'Empresa Contratada';
    protected static ?string $pluralModelLabel = 'Empresas Contratadas';
    protected static ?string $slug = 'empresas-contratadas';
    protected static ?int $navigationSort = 4;

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
            'index' => Pages\ManageEmpresaContratadas::route('/'),
        ];
    }
}

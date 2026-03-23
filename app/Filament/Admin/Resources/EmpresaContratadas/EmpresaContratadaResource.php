<?php

namespace App\Filament\Admin\Resources\EmpresaContratadas;

use App\Filament\Admin\Resources\EmpresaContratadas\Pages\ManageEmpresaContratadas;
use App\Models\EmpresaContratada;
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
use App\Filament\Resources\EmpresaContratadaResource\Pages;
use App\Services\EmpresaContratadaService as Service;
use Filament\Forms\Form;
use UnitEnum;

class EmpresaContratadaResource extends Resource
{
    protected static ?string $model = EmpresaContratada::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice;
    protected static ?string $recordTitleAttribute = 'nome';
    protected static ?string $modelLabel = 'Empresa Contratada';
    protected static ?string $pluralModelLabel = 'Empresas Contratadas';
    protected static ?string $slug = 'empresas-contratadas';
    protected static ?int $navigationSort = 4;


    public static function getGloballySearchableAttributes(): array
    {
        return ['nome', 'cnpj', 'email', 'responsavel'];
    }


    public static function form(Schema $schema): Schema
    {
        return app(Service::class)->configurarFormulario($schema);
    }

    public static function table(Table $table): Table
    {
        return app(Service::class)->configurarTabela($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmpresaContratadas::route('/'),
        ];
    }
}

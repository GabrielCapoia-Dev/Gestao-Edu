<?php

namespace App\Filament\Admin\Resources\Contratos;

use App\Filament\Admin\Resources\Contratos\Pages\CreateContrato;
use App\Filament\Admin\Resources\Contratos\Pages\EditContrato;
use App\Filament\Admin\Resources\Contratos\Pages\ListContratos;
use App\Filament\Admin\Resources\Contratos\RelationManagers\ItensRelationManager;
use App\Filament\Admin\Resources\Contratos\Schemas\ContratoForm;
use App\Filament\Admin\Resources\Contratos\Tables\ContratosTable;
use App\Models\Contrato;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;


class ContratoResource extends Resource
{
    protected static ?string $model = Contrato::class;

    protected static string|BackedEnum|null $navigationIcon = 'fluentui-document-bullet-list-24';
    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';

    protected static ?string $recordTitleAttribute = 'numero_contrato';

    protected static ?string $modelLabel = 'Contrato';

    protected static ?string $pluralModelLabel = 'Contratos';

    public static function form(Schema $schema): Schema
    {
        return ContratoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContratosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItensRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListContratos::route('/'),
            'create' => CreateContrato::route('/create'),
            'edit'   => EditContrato::route('/{record}/edit'),
        ];
    }
}
<?php

namespace App\Filament\Admin\Resources\Laudos;

use App\Filament\Admin\Resources\Laudos\Pages\ManageLaudos;
use App\Models\Laudo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Services\LaudoService;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class LaudoResource extends Resource
{
    public static ?string $modelLabel = 'Laudo';
    public static ?string $pluralModelLabel = 'Laudos';
    public static ?string $slug = 'laudos';
    protected static ?string $model = Laudo::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentPlus;
    protected static string|UnitEnum|null $navigationGroup = 'Gerenciamento Escolar';
    protected static ?string $recordTitleAttribute = 'nome';


    public static function laudoService(): LaudoService
    {
        return app(LaudoService::class);
    }

    public static function form(Schema $schema): Schema
    {
        return static::laudoService()->configurarFormulario($schema);
    }

    public static function table(Table $table): Table
    {
        return static::laudoService()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLaudos::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Laudos;

use App\Filament\Admin\Resources\Laudos\Pages\ManageLaudos;
use App\Models\Laudo;
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
use App\Filament\Resources\LaudoResource\Pages;
use App\Filament\Resources\LaudoResource\RelationManagers;
use App\Services\LaudoService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;


class LaudoResource extends Resource
{
    // protected static ?string $navigationGroup = "Gerenciamento Escolar";
    // protected static ?string $navigationIcon = 'heroicon-o-document-plus';
    public static ?string $modelLabel = 'Laudo';
    public static ?string $pluralModelLabel = 'Laudos';
    public static ?string $slug = 'laudos';
    protected static ?string $model = Laudo::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
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

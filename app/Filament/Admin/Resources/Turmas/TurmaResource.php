<?php

namespace App\Filament\Admin\Resources\Turmas;

use App\Filament\Admin\Resources\Turmas\Pages\ManageTurmas;
use App\Models\Turma;
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
use App\Filament\Resources\TurmaResource\Pages;
use App\Filament\Resources\TurmaResource\RelationManagers;
use App\Models\User;
use App\Services\TurmaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;


class TurmaResource extends Resource
{
    protected static ?string $model = Turma::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::SquaresPlus;

    protected static ?string $recordTitleAttribute = 'serie.nome';

    protected static ?string $navigationLabel = 'Turmas';
    protected static ?string $pluralModelLabel = 'Turmas';
    protected static ?string $modelLabel = 'Turma';
    // protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';


    public static function turmaService(): TurmaService
    {
        return app(TurmaService::class);
    }

    public static function form(Schema $schema): Schema
    {
        return static::turmaService()->configurarFormulario($schema);
    }

    public static function table(Table $table): Table
    {
        return static::turmaService()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTurmas::route('/'),
        ];
    }
}

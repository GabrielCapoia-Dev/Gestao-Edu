<?php

namespace App\Filament\Admin\Resources\Professors;

use App\Filament\Admin\Resources\Professors\Pages\ManageProfessors;
use App\Models\Professor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Services\ProfessorService;
use Illuminate\Support\Facades\Auth;
use UnitEnum;


class ProfessorResource extends Resource
{
    protected static ?string $model = Professor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;
    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Professores';
    protected static ?string $pluralModelLabel = 'Professores';
    protected static ?string $modelLabel = 'Professor';
    protected static ?string $slug = 'professores';
    protected static bool $shouldRegisterNavigation = false;

    public static function getGloballySearchableAttributes(): array
    {
        return ['nome', 'matricula', 'email'];
    }


    public static function professorService(): ProfessorService
    {
        return app(ProfessorService::class);
    }


    public static function form(Schema $schema): Schema
    {
        return static::professorService()->configurarFormulario($schema);
    }

    public static function table(Table $table): Table
    {
        return static::professorService()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProfessors::route('/'),
        ];
    }
}

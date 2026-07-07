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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ProfessorResource extends Resource
{
    protected static ?string $model = Professor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;
    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Professores';
    protected static ?string $pluralModelLabel = 'Professores';
    protected static ?string $modelLabel = 'Professor';
    protected static ?string $slug = 'professores';
    // protected static bool $shouldRegisterNavigation = false;

    public static function getGloballySearchableAttributes(): array
    {
        return ['nome', 'escola.nome', 'matricula', 'email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Professor $record */
        return "{$record->nome}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Professor $record */

        return [
            'Escola' => $record->escola->nome,
            'Matrícula' => $record->matricula,
        ];
    }

    public static function canGloballySearch(): bool
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $user->hasPermissionTo('Listar Professores');
    }

    public static function professorService(): ProfessorService
    {
        return app(ProfessorService::class);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->where('ativo', true)
            ->with(['escola']);
    }

    public static function form(Schema $schema): Schema
    {
        return static::professorService()->configurarFormulario($schema);
    }

    public static function table(Table $table): Table
    {
        return static::professorService()->configurarTabela($table, Auth::user());
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        $policy = Gate::getPolicyFor(static::getModel());

        if (! $user || ! $policy || ! method_exists($policy, 'applyViewAnyScope')) {
            return $query->whereRaw('1 = 0');
        }

        return $policy->applyViewAnyScope($user, $query);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProfessors::route('/'),
        ];
    }
}

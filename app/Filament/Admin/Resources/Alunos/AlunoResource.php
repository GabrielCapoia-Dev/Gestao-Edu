<?php

namespace App\Filament\Admin\Resources\Alunos;

use App\Filament\Admin\Resources\Alunos\Pages\CreateAluno;
use App\Filament\Admin\Resources\Alunos\Pages\EditAluno;
use App\Filament\Admin\Resources\Alunos\Pages\ListAlunos;
use App\Models\Aluno;
use App\Services\AlunoService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AlunoResource extends Resource
{
    protected static ?string $model = Aluno::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationParentItem = 'Turmas';

    protected static ?string $navigationLabel = 'Alunos';

    protected static ?string $pluralModelLabel = 'Alunos';

    protected static ?string $modelLabel = 'Aluno';

    protected static ?string $slug = 'alunos';

    public static function alunoService(): AlunoService
    {
        return app(AlunoService::class);
    }

    public static function canAccess(): bool
    {
        return static::alunoService()->queryVisivel(Auth::user())->exists()
            || (Auth::user()?->hasPermissionTo('Listar Alunos') ?? false);
    }

    public static function canGloballySearch(): bool
    {
        return Auth::user()?->hasPermissionTo('Listar Alunos') ?? false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nome', 'cgm', 'turma.nome', 'turma.serie.nome'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::alunoService()->queryVisivel(Auth::user())
            ->with(['turma.escola', 'turma.serie']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Aluno $record */
        return $record->nome;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Aluno $record */
        return [
            'CGM' => $record->cgm,
            'Turma' => $record->turma?->nome,
            'Série' => $record->turma?->serie?->nome,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return static::alunoService()->configurarFormulario($schema, 'form');
    }

    public static function table(Table $table): Table
    {
        return static::alunoService()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlunos::route('/'),
            'create' => CreateAluno::route('/create'),
            'edit' => EditAluno::route('/{record}/edit'),
        ];
    }
}


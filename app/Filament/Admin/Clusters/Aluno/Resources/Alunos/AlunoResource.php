<?php

namespace App\Filament\Admin\Clusters\Aluno\Resources\Alunos;

use App\Filament\Admin\Clusters\Aluno\AlunoCluster;
use App\Filament\Admin\Clusters\Aluno\Resources\Alunos\Pages\CreateAluno;
use App\Filament\Admin\Clusters\Aluno\Resources\Alunos\Pages\EditAluno;
use App\Filament\Admin\Clusters\Aluno\Resources\Alunos\Pages\ListAlunos;
use App\Filament\Admin\Clusters\Aluno\Resources\Alunos\Schemas\AlunoForm;
use App\Filament\Admin\Clusters\Aluno\Resources\Alunos\Tables\AlunosTable;
use App\Models\Aluno;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AlunoResource extends Resource
{
    protected static ?string $model = Aluno::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = AlunoCluster::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $navigationLabel = 'Alunos';
    protected static ?string $pluralModelLabel = 'Alunos';
    protected static ?string $modelLabel = 'Aluno';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return AlunoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AlunosTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['turma.escola', 'turma.serie', 'professor', 'retencoes.serie']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAlunos::route('/'),
            'create' => CreateAluno::route('/create'),
            'edit'   => EditAluno::route('/{record}/edit'),
        ];
    }
}
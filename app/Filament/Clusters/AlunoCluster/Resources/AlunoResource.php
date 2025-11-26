<?php

namespace App\Filament\Clusters\AlunoCluster\Resources;

use App\Filament\Clusters\AlunoCluster;
use App\Filament\Clusters\AlunoCluster\Resources\AlunoResource\Pages;
use App\Filament\Clusters\AlunoCluster\Resources\AlunoResource\RelationManagers;
use App\Models\Aluno;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Services\AlunoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;


class AlunoResource extends Resource
{
    protected static ?string $model = Aluno::class;


    protected static ?string $cluster = AlunoCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Alunos';
    protected static ?string $pluralModelLabel = 'Alunos';
    protected static ?string $modelLabel = 'Aluno';
    protected static ?string $slug = 'alunos';

    public static function alunoService(): AlunoService
    {
        return app(AlunoService::class);
    }

    public static function form(Form $form): Form
    {
        return static::alunoService()->configurarFormulario($form, Auth::user());
    }

    public static function table(Table $table): Table
    {
        return static::alunoService()->configurarTabela($table, Auth::user());
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['turma.escola', 'turma.serie', 'professor', 'retencoes.serie']);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlunos::route('/'),
            'create' => Pages\CreateAluno::route('/create'),
            'edit' => Pages\EditAluno::route('/{record}/edit'),
        ];
    }
}

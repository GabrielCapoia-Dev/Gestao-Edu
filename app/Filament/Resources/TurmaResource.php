<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TurmaResource\Pages;
use App\Filament\Resources\TurmaResource\RelationManagers;
use App\Models\Turma;
use App\Models\User;
use App\Services\TurmaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TurmaResource extends Resource
{
    protected static ?string $model = Turma::class;

    // protected static ?string $navigationGroup = 'Gerenciamento Escolar';
    // protected static ?string $navigationIcon = 'heroicon-o-squares-plus';
    protected static ?string $navigationLabel = 'Turmas';
    protected static ?string $pluralModelLabel = 'Turmas';
    protected static ?string $modelLabel = 'Turma';


    public static function turmaService(): TurmaService
    {
        return app(TurmaService::class);
    }

    // public static function form(Form $form): Form
    // {
    //     return static::turmaService()->configurarFormulario($form, Auth::user());
    // }

    public static function table(Table $table): Table
    {
        return static::turmaService()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTurmas::route('/'),
        ];
    }
}

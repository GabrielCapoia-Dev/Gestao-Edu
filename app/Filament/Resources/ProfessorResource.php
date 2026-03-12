<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProfessorResource\Pages;
use App\Models\Professor;
use App\Services\ProfessorService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ProfessorResource extends Resource
{
    protected static ?string $model = Professor::class;

    // protected static ?string $navigationIcon = 'heroicon-o-user-group';
    // protected static ?string $navigationGroup = 'Gerenciamento Escolar';
    protected static ?string $navigationLabel = 'Professores';
    protected static ?string $pluralModelLabel = 'Professores';
    protected static ?string $modelLabel = 'Professor';
    protected static ?string $slug = 'professores';


    public static function professorService(): ProfessorService
    {
        return app(ProfessorService::class);
    }

    // public static function form(Form $form): Form
    // {
    //     return static::professorService()->configurarFormulario($form, Auth::user());
    // }

    public static function table(Table $table): Table
    {
        return static::professorService()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProfessors::route('/'),
        ];
    }
}

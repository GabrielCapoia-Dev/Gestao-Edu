<?php

namespace App\Filament\Admin\Clusters\Aluno;

use BackedEnum;
use UnitEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class AlunoCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;
    protected static string|UnitEnum|null $navigationGroup = 'gerenciamento-escolar';
    protected static ?string $navigationLabel = 'Alunos';
    protected static ?string $pluralModelLabel = 'Alunos';
    protected static ?string $modelLabel = 'Aluno';
    protected static ?string $slug = 'grupo-alunos';
}

<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;


class AlunoCluster extends Cluster
{

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Alunos';
    protected static ?string $pluralModelLabel = 'Alunos';
    protected static ?string $modelLabel = 'Aluno';
    protected static ?string $navigationGroup = 'Gerenciamento Escolar';
    protected static ?string $slug = 'grupo-alunos';
}

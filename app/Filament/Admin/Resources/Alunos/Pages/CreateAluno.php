<?php

namespace App\Filament\Admin\Resources\Alunos\Pages;

use App\Filament\Admin\Resources\Alunos\AlunoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAluno extends CreateRecord
{
    protected static string $resource = AlunoResource::class;
}


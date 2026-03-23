<?php

namespace App\Filament\Admin\Clusters\Aluno\Resources\Alunos\Pages;

use App\Filament\Admin\Clusters\Aluno\Resources\Alunos\AlunoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAlunos extends ListRecords
{
    protected static string $resource = AlunoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
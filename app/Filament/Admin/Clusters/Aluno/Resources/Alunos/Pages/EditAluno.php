<?php

namespace App\Filament\Admin\Clusters\Aluno\Resources\Alunos\Pages;


use App\Filament\Admin\Clusters\Aluno\Resources\Alunos\AlunoResource;
use Filament\Resources\Pages\EditRecord;

class EditAluno extends EditRecord
{
    protected static string $resource = AlunoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
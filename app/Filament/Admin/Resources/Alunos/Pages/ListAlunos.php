<?php

namespace App\Filament\Admin\Resources\Alunos\Pages;

use App\Filament\Admin\Resources\Alunos\AlunoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAlunos extends ListRecords
{
    protected static string $resource = AlunoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        if (request()->filled('turma')) {
            return 'Alunos da Turma';
        }

        return parent::getTitle();
    }
}

<?php

namespace App\Filament\Admin\Resources\Alunos\Pages;

use App\Filament\Admin\Resources\Alunos\AlunoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAluno extends EditRecord
{
    protected static string $resource = AlunoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}


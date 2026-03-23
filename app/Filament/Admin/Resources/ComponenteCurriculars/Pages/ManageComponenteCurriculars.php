<?php

namespace App\Filament\Admin\Resources\ComponenteCurriculars\Pages;

use App\Filament\Admin\Resources\ComponenteCurriculars\ComponenteCurricularResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageComponenteCurriculars extends ManageRecords
{
    protected static string $resource = ComponenteCurricularResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

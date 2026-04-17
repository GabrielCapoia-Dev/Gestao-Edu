<?php

namespace App\Filament\Admin\Resources\Alternativas\Pages;

use App\Filament\Admin\Resources\Alternativas\AlternativaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAlternativas extends ManageRecords
{
    protected static string $resource = AlternativaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

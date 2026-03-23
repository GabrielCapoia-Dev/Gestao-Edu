<?php

namespace App\Filament\Admin\Resources\Laudos\Pages;

use App\Filament\Admin\Resources\Laudos\LaudoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLaudos extends ManageRecords
{
    protected static string $resource = LaudoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\TipoStatusResource\Pages;

use App\Filament\Resources\TipoStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTipoStatuses extends ManageRecords
{
    protected static string $resource = TipoStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

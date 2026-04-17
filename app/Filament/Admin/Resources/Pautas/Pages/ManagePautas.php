<?php

namespace App\Filament\Admin\Resources\Pautas\Pages;

use App\Filament\Admin\Resources\Pautas\PautaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePautas extends ManageRecords
{
    protected static string $resource = PautaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

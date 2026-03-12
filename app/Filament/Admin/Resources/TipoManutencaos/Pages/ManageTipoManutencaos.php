<?php

namespace App\Filament\Admin\Resources\TipoManutencaos\Pages;

use App\Filament\Admin\Resources\TipoManutencaos\TipoManutencaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTipoManutencaos extends ManageRecords
{
    protected static string $resource = TipoManutencaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

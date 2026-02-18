<?php

namespace App\Filament\Resources\TipoManutencaoResource\Pages;

use App\Filament\Resources\TipoManutencaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoManutencaos extends ListRecords
{
    protected static string $resource = TipoManutencaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

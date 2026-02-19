<?php

namespace App\Filament\Resources\EmpresaContratadaResource\Pages;

use App\Filament\Resources\EmpresaContratadaResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEmpresaContratadas extends ManageRecords
{
    protected static string $resource = EmpresaContratadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

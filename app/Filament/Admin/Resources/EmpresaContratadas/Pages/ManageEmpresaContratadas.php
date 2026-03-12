<?php

namespace App\Filament\Admin\Resources\EmpresaContratadas\Pages;

use App\Filament\Admin\Resources\EmpresaContratadas\EmpresaContratadaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmpresaContratadas extends ManageRecords
{
    protected static string $resource = EmpresaContratadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

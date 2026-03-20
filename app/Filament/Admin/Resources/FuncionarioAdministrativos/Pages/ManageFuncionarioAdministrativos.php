<?php

namespace App\Filament\Admin\Resources\FuncionarioAdministrativos\Pages;

use App\Filament\Admin\Resources\FuncionarioAdministrativos\FuncionarioAdministrativoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFuncionarioAdministrativos extends ManageRecords
{
    protected static string $resource = FuncionarioAdministrativoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

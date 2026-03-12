<?php

namespace App\Filament\Admin\Resources\Escolas\Pages;

use App\Filament\Admin\Resources\Escolas\EscolaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEscolas extends ManageRecords
{
    protected static string $resource = EscolaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $data['ativo'] = true;
                    return $data;
                }),
        ];
    }
}

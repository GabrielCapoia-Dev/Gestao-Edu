<?php

namespace App\Filament\Resources\EscolaResource\Pages;

use App\Filament\Resources\EscolaResource;
use App\Models\Escola;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEscolas extends ManageRecords
{
    protected static string $resource = EscolaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['ativo'] = true;
                    return $data;
                }),
        ];
    }
}

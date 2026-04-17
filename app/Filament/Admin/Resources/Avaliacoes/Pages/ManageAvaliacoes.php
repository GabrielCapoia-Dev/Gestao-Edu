<?php

namespace App\Filament\Admin\Resources\Avaliacoes\Pages;

use App\Filament\Admin\Resources\Avaliacoes\AvaliacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAvaliacoes extends ManageRecords
{
    protected static string $resource = AvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

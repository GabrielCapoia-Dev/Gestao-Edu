<?php

namespace App\Filament\Admin\Resources\Setors\Pages;

use App\Filament\Admin\Resources\Setors\SetorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSetors extends ManageRecords
{
    protected static string $resource = SetorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

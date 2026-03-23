<?php

namespace App\Filament\Admin\Resources\DominioEmails\Pages;

use App\Filament\Admin\Resources\DominioEmails\DominioEmailResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDominioEmails extends ManageRecords
{
    protected static string $resource = DominioEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Estoques\Pages;

use App\Filament\Admin\Resources\Estoques\EstoqueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEstoque extends EditRecord
{
    protected static string $resource = EstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

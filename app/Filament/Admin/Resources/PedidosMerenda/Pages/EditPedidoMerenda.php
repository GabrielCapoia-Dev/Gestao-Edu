<?php

namespace App\Filament\Admin\Resources\PedidoMerendas\Pages;

use App\Filament\Admin\Resources\PedidoMerendas\PedidoMerendaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPedidoMerenda extends EditRecord
{
    protected static string $resource = PedidoMerendaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

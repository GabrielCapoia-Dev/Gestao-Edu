<?php

namespace App\Filament\Admin\Resources\PedidosMerenda\Pages;

use App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPedidoMerenda extends EditRecord
{
    protected static string $resource = PedidosMerendaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

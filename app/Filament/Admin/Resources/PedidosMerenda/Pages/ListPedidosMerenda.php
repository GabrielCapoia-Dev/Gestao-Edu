<?php

namespace App\Filament\Admin\Resources\PedidosMerenda\Pages;

use App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPedidosMerenda extends ListRecords
{
    protected static string $resource = PedidosMerendaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

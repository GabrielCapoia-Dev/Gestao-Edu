<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Resources\PedidoResource;
use App\Services\PedidoService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePedido extends CreateRecord
{
    protected static string $resource = PedidoResource::class;

    protected function handleRecordCreation(array $data): \App\Models\Pedido
    {
        return app(PedidoService::class)
            ->criarPedido($data, Auth::user());
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}

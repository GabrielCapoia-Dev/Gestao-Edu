<?php

namespace App\Filament\Admin\Resources\Pedidos\Pages;

use App\Filament\Admin\Resources\Pedidos\PedidoResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\PedidoService;
use Illuminate\Support\Facades\Auth;

class CreatePedido extends CreateRecord
{
    protected static string $resource = PedidoResource::class;
    protected static bool $canCreateAnother = false;

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

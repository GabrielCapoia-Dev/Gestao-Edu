<?php

namespace App\Filament\Admin\Resources\InventarioPedidos\Pages;

use App\Filament\Admin\Resources\InventarioPedidos\InventarioPedidoResource;
use App\Models\InventarioPedido;
use App\Services\Inventario\InventarioPedidoService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateInventarioPedido extends CreateRecord
{
    protected static string $resource = InventarioPedidoResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var InventarioPedido */
        return app(InventarioPedidoService::class)->criarPedido(Auth::user(), $data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}

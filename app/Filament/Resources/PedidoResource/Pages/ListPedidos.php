<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Resources\PedidoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use App\Models\TipoStatus;

class ListPedidos extends ListRecords
{
    protected static string $resource = PedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [];

        $statuses = TipoStatus::where('ativo', true)
            ->orderBy('nome')
            ->get();

        foreach ($statuses as $status) {
            $tabs[$status->nome] = Tab::make()
                ->label($status->nome)
                ->modifyQueryUsing(
                    fn($query) =>
                    $query->where('tipo_status_id', $status->id)
                );
        }

        return $tabs;
    }
}

<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Resources\PedidoResource;
use App\Models\TipoStatus;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

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

            $hex = substr(ltrim($status->cor, '#'), 0, 6);

            $tabs[$status->nome] = Tab::make($status->nome)
                ->modifyQueryUsing(
                    fn($query) => $query->where('tipo_status_id', $status->id)
                )
                ->extraAttributes([
                    'style' => "
                    --tab-color: #{$hex};
                    background-color: #{$hex}15;
                    border: 1px solid #{$hex}40;
                    color: #{$hex};
                ",
                ]);
        }

        return $tabs;
    }
}

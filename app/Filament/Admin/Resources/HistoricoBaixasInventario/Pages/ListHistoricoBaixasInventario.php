<?php

namespace App\Filament\Admin\Resources\HistoricoBaixasInventario\Pages;

use App\Filament\Admin\Resources\HistoricoBaixasInventario\HistoricoBaixasInventarioResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListHistoricoBaixasInventario extends ListRecords
{
    protected static string $resource = HistoricoBaixasInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gestaoInventario')
                ->label('Voltar ao Inventário')
                ->url(route('filament.admin.pages.gestao-inventario', array_filter([
                    'inventario' => request()->integer('inventario') ?: null,
                ]))),
        ];
    }
}

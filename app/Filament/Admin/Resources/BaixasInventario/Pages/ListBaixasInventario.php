<?php

namespace App\Filament\Admin\Resources\BaixasInventario\Pages;

use App\Filament\Admin\Resources\BaixasInventario\BaixasInventarioResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListBaixasInventario extends ListRecords
{
    protected static string $resource = BaixasInventarioResource::class;

            public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Alimentação Escolar',
            'title' => "Baixas de Inventário",
            'description' => 'Relatório de Baixas de Inventário realizadas no período selecionado.',
        ]);
    }


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

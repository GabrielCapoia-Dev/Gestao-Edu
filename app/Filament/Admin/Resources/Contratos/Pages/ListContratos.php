<?php

namespace App\Filament\Admin\Resources\Contratos\Pages;

use App\Filament\Admin\Resources\Contratos\ContratoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListContratos extends ListRecords
{
    protected static string $resource = ContratoResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Alimentação Escolar',
            'title' => 'Contratos',
            'description' => 'Gerencie os contratos de fornecimento de alimentos, adicione novos contratos e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

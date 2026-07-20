<?php

namespace App\Filament\Admin\Resources\Avisos\Pages;

use App\Filament\Admin\Resources\Avisos\AvisoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListAvisos extends ListRecords
{
    protected static string $resource = AvisoResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Página inicial',
            'title' => 'Avisos',
            'description' => 'Cadastre, agende e direcione os avisos exibidos na página inicial.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo aviso'),
        ];
    }
}

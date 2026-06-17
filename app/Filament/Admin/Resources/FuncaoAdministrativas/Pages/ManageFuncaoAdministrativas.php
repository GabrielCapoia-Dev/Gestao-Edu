<?php

namespace App\Filament\Admin\Resources\FuncaoAdministrativas\Pages;

use App\Filament\Admin\Resources\FuncaoAdministrativas\FuncaoAdministrativaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManageFuncaoAdministrativas extends ManageRecords
{
    protected static string $resource = FuncaoAdministrativaResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Cadastros',
            'title' => 'Funções Administrativas',
            'description' => 'Gerencie funções, vínculos pedagógicos e marcadores usados em documentos e relatórios.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Criar função administrativa'),
        ];
    }
}

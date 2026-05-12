<?php

namespace App\Filament\Admin\Resources\EmpresaContratadas\Pages;

use App\Filament\Admin\Resources\EmpresaContratadas\EmpresaContratadaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManageEmpresaContratadas extends ManageRecords
{
    protected static string $resource = EmpresaContratadaResource::class;


    public function getHeader(): ?View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => 'Empresas Contratadas',
            'description' => 'Gerencie as empresas contratadas, adicione novas instituições e mantenha um registro atualizado das informações.',
        ]);
    }


    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

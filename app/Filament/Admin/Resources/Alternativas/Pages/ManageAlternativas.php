<?php

namespace App\Filament\Admin\Resources\Alternativas\Pages;

use App\Filament\Admin\Resources\Alternativas\AlternativaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAlternativas extends ManageRecords
{
    protected static string $resource = AlternativaResource::class;

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => "Alternativas",
            'description' => 'Gerencie as alternativas, adicione novas e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

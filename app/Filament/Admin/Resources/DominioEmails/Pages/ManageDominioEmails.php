<?php

namespace App\Filament\Admin\Resources\DominioEmails\Pages;

use App\Filament\Admin\Resources\DominioEmails\DominioEmailResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManageDominioEmails extends ManageRecords
{
    protected static string $resource = DominioEmailResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Acesso',
            'title' => 'Domínios de e-mail',
            'description' => 'Gerencie os domínios de e-mail, adicione novos domínios e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Setors\Pages;

use App\Filament\Admin\Resources\Setors\SetorResource;
use Filament\Actions\CreateAction;
use Illuminate\Contracts\View\View;
use Filament\Resources\Pages\ManageRecords;

class ManageSetors extends ManageRecords
{
    protected static string $resource = SetorResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Manutenção',
            'title' => 'Setores',
            'description' => 'Gerencie os setores para organizar fluxos do sistema.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

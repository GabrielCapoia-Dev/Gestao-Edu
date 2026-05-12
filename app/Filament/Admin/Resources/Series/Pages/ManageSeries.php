<?php

namespace App\Filament\Admin\Resources\Series\Pages;

use App\Filament\Admin\Resources\Series\SerieResource;
use Filament\Actions\CreateAction;
use Illuminate\Contracts\View\View;
use Filament\Resources\Pages\ManageRecords;

class ManageSeries extends ManageRecords
{
    protected static string $resource = SerieResource::class;

    
    public function getHeader(): ?View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => 'Séries',
            'description' => 'Gerencie as séries para organizar o currículo escolar dos alunos.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

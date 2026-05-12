<?php

namespace App\Filament\Admin\Resources\Pautas\Pages;

use App\Filament\Admin\Resources\Pautas\PautaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManagePautas extends ManageRecords
{
    protected static string $resource = PautaResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => "Pautas",
            'description' => 'Gerencie as pautas de avaliação, configure critérios de avaliação e mantenha um registro detalhado para cada uma.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

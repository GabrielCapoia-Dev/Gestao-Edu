<?php

namespace App\Filament\Admin\Resources\ComponenteCurriculars\Pages;

use App\Filament\Admin\Resources\ComponenteCurriculars\ComponenteCurricularResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManageComponenteCurriculars extends ManageRecords
{
    protected static string $resource = ComponenteCurricularResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => "Componentes Curriculares",
            'description' => 'Gerencie os componentes curriculares, adicione novos e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

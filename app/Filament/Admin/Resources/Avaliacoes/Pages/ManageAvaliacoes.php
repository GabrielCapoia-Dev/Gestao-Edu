<?php

namespace App\Filament\Admin\Resources\Avaliacoes\Pages;

use App\Filament\Admin\Resources\Avaliacoes\AvaliacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAvaliacoes extends ManageRecords
{
    protected static string $resource = AvaliacaoResource::class;

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => "Avaliações",
            'description' => 'Gerencie as avaliações, adicione novas e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Professors\Pages;

use App\Filament\Admin\Resources\Professors\ProfessorResource;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use App\Services\ProfessorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\View;

class ManageProfessors extends ManageRecords
{
    protected static string $resource = ProfessorResource::class;

    public function mount(): void
    {
        $this->redirect(
            ServidorResource::getUrl('index', ['activeTab' => 'professores']),
            navigate: false,
        );
    }

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => 'Gerenciar Professores',
            'description' => 'Organize e gerencie as informações dos professores da rede.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->closeModalByClickingAway(false)
                ->mutateDataUsing(function (array $data): array {
                    return app(ProfessorService::class)
                        ->forcarVinculoComEscola($data, Auth::user());
                }),
        ];
    }
}

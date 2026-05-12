<?php

namespace App\Filament\Admin\Resources\Escolas\Pages;

use App\Filament\Admin\Resources\Escolas\EscolaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManageEscolas extends ManageRecords
{
    protected static string $resource = EscolaResource::class;

        public function getHeader(): ?View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => "Escolas",
            'description' => 'Gerencie as escolas da rede educacional, adicione novas instituições e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $data['ativo'] = true;
                    return $data;
                }),
        ];
    }
}

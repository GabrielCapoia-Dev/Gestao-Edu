<?php

namespace App\Filament\Admin\Resources\FuncaoAdministrativas\Pages;

use App\Filament\Admin\Resources\FuncaoAdministrativas\FuncaoAdministrativaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManageFuncaoAdministrativas extends ManageRecords
{
    protected static string $resource = FuncaoAdministrativaResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => "Funções Administrativas",
            'description' => 'Gerencie as Funções Administrativas disponíveis para a equipe gestora, adicione novas funções e mantenha um registro atualizado das atribuições.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function mutateFormDataUsing(array $data): array
    {
        $data['portaria'] = "{$data['portaria_numero']}/{$data['portaria_ano']}";

        unset($data['portaria_numero'], $data['portaria_ano']);

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (!empty($data['portaria']) && str_contains($data['portaria'], '/')) {
            [$num, $ano] = explode('/', $data['portaria']);
            $data['portaria_numero'] = $num;
            $data['portaria_ano'] = $ano;
        }

        return $data;
    }
}

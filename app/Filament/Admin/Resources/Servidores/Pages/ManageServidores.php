<?php

namespace App\Filament\Admin\Resources\Servidores\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Servidor;
use App\Services\ServidorService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManageServidores extends ManageRecords
{
    protected static string $resource = ServidorResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Cadastros',
            'title' => 'Servidores',
            'description' => 'Gerencie servidores, vínculos funcionais e funções administrativas.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo servidor')
                ->slideOver()
                ->closeModalByClickingAway(false)
                ->using(function (array $data): Servidor {
                    $funcaoIds = $data['funcao_administrativa_ids'] ?? [];
                    unset($data['funcao_administrativa_ids']);

                    return app(ServidorService::class)->criarServidorComFuncoes($data, $funcaoIds);
                }),
        ];
    }
}

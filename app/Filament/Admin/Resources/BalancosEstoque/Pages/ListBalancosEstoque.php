<?php

namespace App\Filament\Admin\Resources\BalancosEstoque\Pages;

use App\Filament\Admin\Resources\BalancosEstoque\BalancoEstoqueResource;
use App\Services\Estoque\BalancoEstoqueService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListBalancosEstoque extends ListRecords
{
    protected static string $resource = BalancoEstoqueResource::class;

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Alimentação Escolar',
            'title' => 'Balanços de Estoque',
            'description' => 'Gerencie os balanços de estoque, agende novos balanços e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Agendar Balanço')
                ->modalHeading('Agendar balanço de estoque')
                ->modalSubmitActionLabel('Agendar')
                ->createAnother(false)
                ->using(function (array $data) {
                    return app(BalancoEstoqueService::class)->agendar($data, Auth::user());
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Balanço agendado com sucesso.')
                ),
        ];
    }
}

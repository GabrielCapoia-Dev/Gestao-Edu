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

<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Resources\PedidoResource;
use App\Models\TipoStatus;
use App\Services\PedidoService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ViewPedido extends ViewRecord
{
    protected static string $resource = PedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label('Baixar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn() => route('pedidos.pdf', $this->record))
                ->visible(fn() => User::authUser()->hasPermissionTo('Visualizar Arquivos de Pedidos'))
                ->openUrlInNewTab(),
        ];
    }

    // public function infolist(Infolist $infolist): Infolist
    // {
    //     $user = Auth::user();

    //     return $infolist
    //         ->schema([

    //             /*
    //         |--------------------------------------------------------------------------
    //         | IDENTIFICAÇÃO
    //         |--------------------------------------------------------------------------
    //         */

    //             Infolists\Components\ViewEntry::make('header')
    //                 ->view('components.pedido.pedido-cabecalho')
    //                 ->viewData([
    //                     'record' => $this->record,
    //                 ])
    //                 ->columnSpanFull(),


    //         ]);
    // }
}

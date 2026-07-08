<?php

namespace App\Filament\Admin\Resources\Pedidos\Pages;

use App\Filament\Admin\Resources\Pedidos\PedidoResource;
use App\Models\TipoStatus;
use App\Services\PedidoService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use App\Models\Pedido;
use Illuminate\Support\Facades\Gate;

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
                ->visible(fn () => Gate::allows('viewFiles', Pedido::class))
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

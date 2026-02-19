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

class ViewPedido extends ViewRecord
{
    protected static string $resource = PedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $user = Auth::user();

        return $infolist
            ->schema([

                /*
            |--------------------------------------------------------------------------
            | IDENTIFICAÇÃO
            |--------------------------------------------------------------------------
            */
                Infolists\Components\Section::make('Identificação')
                    ->schema([
                        Infolists\Components\TextEntry::make('numero_protocolo')
                            ->label('Protocolo')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('tipoStatus.nome')
                            ->label('Status')
                            ->badge(),

                        Infolists\Components\TextEntry::make('nivel_prioridade')
                            ->label('Prioridade')
                            ->badge()
                            ->color(fn($state) => match ($state?->value ?? $state) {
                                'Emergencial' => 'danger',
                                'Preventivo'  => 'warning',
                                'Corretivo'   => 'info',
                                default       => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('data_solicitacao')
                            ->label('Data da Solicitação')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(4),

                /*
            |--------------------------------------------------------------------------
            | INFORMAÇÕES DO PEDIDO
            |--------------------------------------------------------------------------
            */
                Infolists\Components\Section::make('Detalhes do Pedido')
                    ->schema([
                        Infolists\Components\TextEntry::make('tipoManutencao.nome')
                            ->label('Tipo de Manutenção'),

                        Infolists\Components\TextEntry::make('escola.nome')
                            ->label('Escola'),

                        Infolists\Components\TextEntry::make('solicitante.name')
                            ->label('Solicitante'),

                        Infolists\Components\TextEntry::make('descricao_pedido')
                            ->label('Descrição')
                            ->columnSpanFull()
                            ->markdown(),
                    ])
                    ->columns(3),

                /*
            |--------------------------------------------------------------------------
            | GESTÃO (Somente quem pode gerenciar)
            |--------------------------------------------------------------------------
            */
                Infolists\Components\Section::make('Gestão')
                    ->schema([
                        Infolists\Components\TextEntry::make('responsavel.name')
                            ->label('Responsável'),

                        Infolists\Components\TextEntry::make('empresaContratada.nome')
                            ->label('Empresa'),

                        Infolists\Components\TextEntry::make('data_prevista')
                            ->label('Data Prevista')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('data_entrega')
                            ->label('Data de Entrega')
                            ->date('d/m/Y'),
                    ])
                    ->columns(4)
                    ->visible(fn() => app(PedidoService::class)
                        ->podeGerenciarPedidos($user)),

            ]);
    }
}

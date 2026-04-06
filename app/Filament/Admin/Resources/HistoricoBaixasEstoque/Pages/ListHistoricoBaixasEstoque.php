<?php

namespace App\Filament\Admin\Resources\HistoricoBaixasEstoque\Pages;

use App\Filament\Admin\Resources\HistoricoBaixasEstoque\HistoricoBaixasEstoqueResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListHistoricoBaixasEstoque extends ListRecords
{
    protected static string $resource = HistoricoBaixasEstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('novaBaixa')
                ->label('Tela de Baixas')
                ->icon('heroicon-o-arrow-trending-down')
                ->url(route('filament.admin.resources.baixas-estoque.index')),
            Action::make('exportarRelatorio')
                ->label('Exportar Relatorio')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn() => route('baixas-estoque.relatorio', request()->query()))
                ->openUrlInNewTab(),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\HistoricoBaixasEstoque\Pages;

use App\Filament\Admin\Resources\HistoricoBaixasEstoque\HistoricoBaixasEstoqueResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListHistoricoBaixasEstoque extends ListRecords
{
    protected static string $resource = HistoricoBaixasEstoqueResource::class;

    public function getHeader(): ?View
{
    return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
        'actions' => $this->getCachedHeaderActions(),

        'eyebrow' => 'Alimentação Escolar',
        'title' => "Histórico de Baixas de Estoque",
        'description' => 'Relatório de Baixas de Estoque realizadas no período selecionado.',
    ]);
}

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

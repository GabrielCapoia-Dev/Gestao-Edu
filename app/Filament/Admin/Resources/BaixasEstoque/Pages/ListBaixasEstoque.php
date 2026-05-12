<?php

namespace App\Filament\Admin\Resources\BaixasEstoque\Pages;

use App\Filament\Admin\Resources\BaixasEstoque\BaixasEstoqueResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListBaixasEstoque extends ListRecords
{
    protected static string $resource = BaixasEstoqueResource::class;

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Alimentação Escolar',
            'title' => "Baixas de Estoque",
            'description' => 'Relatório de Baixas de Estoque realizadas no período selecionado.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('historico')
                ->label('Listagem de Baixas')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(route('filament.admin.resources.historico-baixas-estoque.index')),
            Action::make('estoque')
                ->label('Gestão de Estoque')
                ->icon('heroicon-o-building-storefront')
                ->url(route('filament.admin.pages.gestao-estoque')),
        ];
    }
}

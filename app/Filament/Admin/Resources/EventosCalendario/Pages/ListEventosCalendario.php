<?php

namespace App\Filament\Admin\Resources\EventosCalendario\Pages;

use App\Filament\Admin\Resources\EventosCalendario\EventoCalendarioResource;
use App\Models\ImportacaoEventoCalendario;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ListEventosCalendario extends ListRecords
{
    protected static string $resource = EventoCalendarioResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Início',
            'title' => 'Eventos da agenda',
            'description' => 'Cadastre eventos, acompanhe publicações e importe calendários por planilha.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        $user = EventoCalendarioResource::usuarioEfetivo();

        return [
            Action::make('importar')
                ->label('Importar planilha')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => $user && Gate::forUser($user)->allows('create', ImportacaoEventoCalendario::class))
                ->url(EventoCalendarioResource::getUrl('import')),
            CreateAction::make(),
        ];
    }
}

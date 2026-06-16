<?php

namespace App\Filament\Admin\Resources\TipoManutencaos\Pages;

use App\Filament\Admin\Resources\TipoManutencaos\TipoManutencaoResource;
use App\Models\TipoManutencao;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ManageTipoManutencaos extends ManageRecords
{
    protected static string $resource = TipoManutencaoResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Manutenção',
            'title' => 'Tipos de Manutenção',
            'description' => 'Gerencie os tipos de manutenção para organizar as demandas de manutenção para unidades escolares.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): TipoManutencao {
                    $opcoes = $data['opcoes_data'] ?? [];
                    unset($data['opcoes_data']);

                    $tipo = TipoManutencao::create([
                        ...$data,
                        'alterado_por' => Auth::user()?->name,
                    ]);

                    app(\App\Services\TipoManutencaoService::class)->salvarOpcoes($tipo, $opcoes);

                    return $tipo;
                }),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\TipoManutencaos\Pages;

use App\Filament\Admin\Resources\TipoManutencaos\TipoManutencaoResource;
use App\Models\TipoManutencao;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageTipoManutencaos extends ManageRecords
{
    protected static string $resource = TipoManutencaoResource::class;

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

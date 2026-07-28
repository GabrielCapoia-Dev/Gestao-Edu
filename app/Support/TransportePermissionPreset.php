<?php

namespace App\Support;

use App\Models\Enums\ListaPermissoes;

class TransportePermissionPreset
{
    /** @return array<int, ListaPermissoes> */
    public static function permissions(): array
    {
        return [
            ListaPermissoes::AcessarPainel,
            ListaPermissoes::VisualizarTelaDeInicio,
            ListaPermissoes::VisualizarAgendaDeTodaARede,
            ListaPermissoes::ListarReservasVeiculos,
            ListaPermissoes::ListarEventosTransporte,
            ListaPermissoes::PublicarEventosTransporte,
            ListaPermissoes::DesativarEventosTransporte,
            ListaPermissoes::RejeitarEventosTransporte,
        ];
    }

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_values(array_unique(array_map(
            static fn (ListaPermissoes $permission): string => $permission->label(),
            static::permissions(),
        )));
    }
}

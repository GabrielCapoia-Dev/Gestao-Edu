<?php

namespace App\Support;

use App\Models\Enums\ListaPermissoes;

class ManutencaoPermissionPreset
{
    /** @return array<int, ListaPermissoes> */
    public static function permissions(): array
    {
        return [
            ListaPermissoes::AcessarEscopoGlobalDeSetores,
            ListaPermissoes::VisualizarTelaDeInicio,
            ListaPermissoes::ListarPedidos,
            ListaPermissoes::ListarTodosOsPedidos,
            ListaPermissoes::EditarPedidos,
            ListaPermissoes::ComentarPedidos,
            ListaPermissoes::EncaminharPedidosParaSetor,
            ListaPermissoes::VisualizarPedidosPorStatus,
            ListaPermissoes::VisualizarStatusEncaminhadoAoSetor,
            ListaPermissoes::VisualizarHistoricoDePedidos,
            ListaPermissoes::VisualizarArquivosDePedidos,
            ListaPermissoes::VisualizarAgendaDeTodaARede,
            ListaPermissoes::ListarReservasVeiculos,
            ListaPermissoes::CriarReservasVeiculos,
            ListaPermissoes::EditarReservasVeiculos,
            ListaPermissoes::CancelarReservasVeiculos,
            ListaPermissoes::ExportarArquivosPedido,
            ListaPermissoes::ListarTipoManutencao,
            ListaPermissoes::CriarTipoManutencao,
            ListaPermissoes::EditarTipoManutencao,
            ListaPermissoes::VisualizarFeedbackDePedidos,
            ListaPermissoes::ExportarRelatorios,
            ListaPermissoes::VisualizarNotificacoes,
            ListaPermissoes::CriarNotificacoes,
            ListaPermissoes::VisualizarNotificacaoVencimentoDePedidos,
            ListaPermissoes::VisualizarNotificacaoPedidosAtrasados,
            ListaPermissoes::VisualizarNotificacaoPedidosEmergenciais,
            ListaPermissoes::VisualizarNotificacaoPedidoReaberto,
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

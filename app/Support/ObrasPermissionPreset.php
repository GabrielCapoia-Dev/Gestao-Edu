<?php

namespace App\Support;

use App\Models\Enums\ListaPermissoes;

class ObrasPermissionPreset
{
    /** @return array<int, ListaPermissoes> */
    public static function permissions(): array
    {
        return [
            ListaPermissoes::VisualizarTelaDeInicio,
            ListaPermissoes::ComentarPedidos,
            ListaPermissoes::CriarNotificacoes,
            ListaPermissoes::CriarTipoManutencao,
            ListaPermissoes::EditarPedidos,
            ListaPermissoes::EditarTipoManutencao,
            ListaPermissoes::EncaminharPedidosParaSetor,
            ListaPermissoes::ExportarArquivosPedido,
            ListaPermissoes::ExportarRelatorios,
            ListaPermissoes::ListarPedidos,
            ListaPermissoes::ListarTipoManutencao,
            ListaPermissoes::VisualizarArquivosDePedidos,
            ListaPermissoes::VisualizarFeedbackDePedidos,
            ListaPermissoes::VisualizarHistoricoDePedidos,
            ListaPermissoes::VisualizarNotificacaoPedidoReaberto,
            ListaPermissoes::VisualizarNotificacaoPedidosAtrasados,
            ListaPermissoes::VisualizarNotificacaoPedidosEmergenciais,
            ListaPermissoes::VisualizarNotificacaoVencimentoDePedidos,
            ListaPermissoes::VisualizarNotificacoes,
            ListaPermissoes::VisualizarPedidosPorStatus,
            ListaPermissoes::VisualizarStatusEncaminhadoAoSetor,
            ListaPermissoes::CriarEmpresaContratada,
            ListaPermissoes::EditarEmpresaContratada,
            ListaPermissoes::EnviarPedidosParaEmpresa,
            ListaPermissoes::ExcluirEmpresaContratada,
            ListaPermissoes::ExcluirEmpresaContratadaEmMassa,
            ListaPermissoes::ListarEmpresaContratada,
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

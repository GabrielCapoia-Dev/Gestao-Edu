<?php

namespace App\Support;

use App\Models\Enums\ListaPermissoes;

class EquipeGestoraPermissionPreset
{
    /** @return array<int, ListaPermissoes> */
    public static function permissions(): array
    {
        return [
            ListaPermissoes::ListarTurmas,
            ListaPermissoes::ListarAlunos,
            ListaPermissoes::AcompanharAvaliacoes,
            ListaPermissoes::AvaliarPedidos,
            ListaPermissoes::CriarPedidos,
            ListaPermissoes::EditarAlunos,
            ListaPermissoes::EditarProfessores,
            ListaPermissoes::EditarDadosDoProfessor,
            ListaPermissoes::EditarTurmas,
            ListaPermissoes::EditarDadosDaTurma,
            ListaPermissoes::ExcluirProfessores,
            ListaPermissoes::ExcluirTurmas,
            ListaPermissoes::ExportarAvaliacoes,
            ListaPermissoes::FiltrarProfessoresPorComponente,
            ListaPermissoes::FiltrarProfessoresPorSerie,
            ListaPermissoes::GerarParecerDeTransferencia,
            ListaPermissoes::GerenciarImpedimentoDeMatriculaPorFaltaDeTransferencia,
            ListaPermissoes::ListarAvaliacoes,
            ListaPermissoes::ListarPedidos,
            ListaPermissoes::ListarProfessores,
            ListaPermissoes::NotificarImpedimentoDeMatriculaPorFaltaDeTransferencia,
            ListaPermissoes::NotificarStatusPendente,
            ListaPermissoes::PreencherAvaliacoesEmMassa,
            ListaPermissoes::RealizarRemanejamentoDeAluno,
            ListaPermissoes::RealizarTransferenciaDeAluno,
            ListaPermissoes::ResponderAvaliacoes,
            ListaPermissoes::VincularPedidosAdicionais,
            ListaPermissoes::VisualizarProfessores,
            ListaPermissoes::VisualizarHistoricoDePedidos,
            ListaPermissoes::VisualizarTelaDeInicio,
            ListaPermissoes::AcessarPainel,
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

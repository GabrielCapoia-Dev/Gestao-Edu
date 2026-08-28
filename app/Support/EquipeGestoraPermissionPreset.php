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
            ListaPermissoes::ListarPessoas,
            ListaPermissoes::EditarPessoas,
            ListaPermissoes::EditarDadosDePessoas,
            ListaPermissoes::EditarTurmasEComponentesDePessoas,
            ListaPermissoes::EditarTurmas,
            ListaPermissoes::EditarDadosDaTurma,
            ListaPermissoes::ExcluirTurmas,
            ListaPermissoes::ExportarAvaliacoes,
            ListaPermissoes::FiltrarProfessoresPorSerie,
            ListaPermissoes::GerarParecerDeTransferencia,
            ListaPermissoes::GerenciarImpedimentoDeMatriculaPorFaltaDeTransferencia,
            ListaPermissoes::ListarAvaliacoes,
            ListaPermissoes::ListarPedidos,
            ListaPermissoes::NotificarImpedimentoDeMatriculaPorFaltaDeTransferencia,
            ListaPermissoes::NotificarStatusPendente,
            ListaPermissoes::PreencherAvaliacoesEmMassa,
            ListaPermissoes::RealizarRemanejamentoDeAluno,
            ListaPermissoes::RealizarTransferenciaDeAluno,
            ListaPermissoes::ResponderAvaliacoes,
            ListaPermissoes::ConcluirAvaliacoes,
            ListaPermissoes::ReabrirAvaliacoes,
            ListaPermissoes::VincularPedidosAdicionais,
            ListaPermissoes::VisualizarAgendaDeTodaARede,
            ListaPermissoes::VisualizarHistoricoDePedidos,
            ListaPermissoes::VisualizarTelaDeInicio,
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

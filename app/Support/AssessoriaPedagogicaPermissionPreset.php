<?php

namespace App\Support;

use App\Models\Enums\ListaPermissoes;

class AssessoriaPedagogicaPermissionPreset
{
    /** @return array<int, ListaPermissoes> */
    public static function permissions(): array
    {
        return [
            ListaPermissoes::VisualizarTelaDeInicio,
            ListaPermissoes::ListarEscolas,
            ListaPermissoes::ListarSeries,
            ListaPermissoes::ListarTurmas,
            ListaPermissoes::ListarAlunos,
            ListaPermissoes::VisualizarDetalhesDeAluno,
            ListaPermissoes::FiltrarAlunosPorEscola,
            ListaPermissoes::FiltrarTurmasPorEscola,
            ListaPermissoes::ListarPessoas,
            ListaPermissoes::ListarServidores,
            ListaPermissoes::ListarAvisos,
            ListaPermissoes::ListarComponenteCurricular,
            ListaPermissoes::ListarAlternativas,
            ListaPermissoes::ListarPautas,
            ListaPermissoes::ListarAvaliacoes,
            ListaPermissoes::ListarTiposDeAvaliacoes,
            ListaPermissoes::AcompanharAvaliacoes,
            ListaPermissoes::VisualizarProgressoPorEscola,
            ListaPermissoes::ListarRelatoriosProfessorPorComponenteETurma,
            ListaPermissoes::ListarRelatoriosComponentesComProfessoresFaltando,
            ListaPermissoes::ListarRelatoriosDashboard,
            ListaPermissoes::ExportarAlunos,
            ListaPermissoes::ExportarTurmas,
            ListaPermissoes::ExportarEscolas,
            ListaPermissoes::ExportarProfessores,
            ListaPermissoes::ExportarRelatorios,
            ListaPermissoes::ExportarAvaliacoes,
            ListaPermissoes::ExportarComponenteCurricular,
            ListaPermissoes::ListarMeusEventos,
            ListaPermissoes::ListarReservasVeiculos,
            ListaPermissoes::CriarEventos,
            ListaPermissoes::CriarEventosTransporte,
            ListaPermissoes::GerenciarPublicoAlvoDeEventos,
            ListaPermissoes::CriarReservasVeiculos,
            ListaPermissoes::EditarEventos,
            ListaPermissoes::EditarReservasVeiculos,
            ListaPermissoes::CancelarReservasVeiculos,
            ListaPermissoes::PublicarEventos,
            ListaPermissoes::DesativarEventos,
            ListaPermissoes::CriarAvisos,
            ListaPermissoes::EditarAvisos,
            ListaPermissoes::GerenciarPublicoAlvoDeAvisos,
            ListaPermissoes::PublicarAvisos,
            ListaPermissoes::VisualizarUsuariosOnline,
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

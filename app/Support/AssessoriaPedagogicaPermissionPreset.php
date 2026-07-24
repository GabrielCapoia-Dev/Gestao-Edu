<?php

namespace App\Support;

use App\Models\Enums\ListaPermissoes;

class AssessoriaPedagogicaPermissionPreset
{
    /** @return array<int, ListaPermissoes> */
    public static function permissions(): array
    {
        return [
            ListaPermissoes::AcessarPainel,
            ListaPermissoes::VisualizarTelaDeInicio,
            ListaPermissoes::VisualizarAgendaDeTodaARede,
            ListaPermissoes::AcessarEscopoGlobalDeSetores,
            ListaPermissoes::ListarEscolas,
            ListaPermissoes::ListarSeries,
            ListaPermissoes::ListarTurmas,
            ListaPermissoes::ListarAlunos,
            ListaPermissoes::VisualizarDetalhesDeAluno,
            ListaPermissoes::FiltrarAlunosPorEscola,
            ListaPermissoes::FiltrarTurmasPorEscola,
            ListaPermissoes::ListarPessoas,
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
            ListaPermissoes::ListarMeusEventos,
            ListaPermissoes::CriarEventos,
            ListaPermissoes::CriarEventosTransporte,
            ListaPermissoes::EditarEventos,
            ListaPermissoes::PublicarEventos,
            ListaPermissoes::DesativarEventos,
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

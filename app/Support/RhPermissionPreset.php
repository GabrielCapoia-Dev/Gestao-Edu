<?php

namespace App\Support;

use App\Models\Enums\ListaPermissoes;

class RhPermissionPreset
{
    /** @return array<int, ListaPermissoes> */
    public static function permissions(): array
    {
        return [
            ListaPermissoes::VisualizarTelaDeInicio,
            ListaPermissoes::ListarSaldoEleitoral,
            ListaPermissoes::GerenciarSaldoEleitoral,
            ListaPermissoes::ListarPessoas,
            ListaPermissoes::CriarPessoas,
            ListaPermissoes::CriarUsuarios,
            ListaPermissoes::EditarPessoas,
            ListaPermissoes::EditarDadosDePessoas,
            ListaPermissoes::EditarUsuarios,
            ListaPermissoes::ExcluirPessoasDefinitivamente,
            ListaPermissoes::GerenciarVinculosEstruturaisDePessoas,
            ListaPermissoes::ExcluirPessoas,
            ListaPermissoes::ListarEscolas,
            ListaPermissoes::ExportarEscolas,
            ListaPermissoes::GerenciarLotacoes,
            ListaPermissoes::ListarRelatoriosDashboard,
            ListaPermissoes::ListarRelatoriosProfessorPorComponenteETurma,
            ListaPermissoes::ListarRelatoriosComponentesComProfessoresFaltando,
            ListaPermissoes::ExportarRelatorios,
            ListaPermissoes::ListarReservasVeiculos,
            ListaPermissoes::CriarReservasVeiculos,
            ListaPermissoes::EditarReservasVeiculos,
            ListaPermissoes::CancelarReservasVeiculos,
            ListaPermissoes::VisualizarAgendaDeTodaARede,
            ListaPermissoes::ListarEventosGeral,
            ListaPermissoes::ListarMeusEventos,
            ListaPermissoes::CriarEventos,
            ListaPermissoes::EditarEventos,
            ListaPermissoes::PublicarEventos,
            ListaPermissoes::DesativarEventos,
            ListaPermissoes::GerenciarPublicoAlvoDeEventos,
            ListaPermissoes::ListarAvisos,
            ListaPermissoes::CriarAvisos,
            ListaPermissoes::EditarAvisos,
            ListaPermissoes::ExcluirAvisos,
            ListaPermissoes::GerenciarPublicoAlvoDeAvisos,
            ListaPermissoes::PublicarAvisos,
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

<?php

namespace App\Http\Controllers;

use App\Relatorios\Relatorios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RelatorioController extends Controller
{
    /**
     * Ficha de um unico objeto (modelo).
     * Espera: view, data (JSON base64), tipo=ficha
     */
    public function ficha(Request $request)
    {
        $this->autorizarExportacaoDeAlunos();

        $view = $request->query('view'); // ex.: relatorios.alunos.ficha
        $tipo = $request->query('tipo', Relatorios::TIPO_FICHA);

        $dataEncoded = $request->query('data');
        $data = $this->decodeData($dataEncoded);

        return Relatorios::gerarRelatorio(
            view: $view,
            data: $data,
            tipo: $tipo,
        );
    }

    /**
     * Lista (bulkList) de varios objetos.
     */
    public function bulkList(Request $request)
    {
        $this->autorizarExportacaoDeAlunos();
        // Recupera o pacote salvo na sessao
        $payload = $request->session()->get('relatorios.bulk_list');

        if (! $payload) {
            abort(400, 'Nenhum dado encontrado para gerar o relatorio.');
        }

        $view = $payload['view'] ?? null;
        $data = $payload['data'] ?? [];
        $tipo = $payload['tipo'] ?? Relatorios::TIPO_BULK_LIST;

        if (! $view) {
            abort(400, 'View do relatorio nao informada.');
        }

        // Aqui voce chama sua classe geradora de PDF.
        // Ajuste o metodo conforme sua implementacao atual.
        // Exemplo:
        return Relatorios::gerarRelatorio(
            view: $view,
            data: $data,
            tipo: $tipo,
        );

        // Ou, se for um metodo de instancia:
        // $rel = new Relatorios();
        // return $rel->gerarRelatorio($view, $data, $tipo);
    }

    /**
     * Fichas em lote (bulkFicha) - varias fichas detalhadas.
     */
    public function bulkFicha(Request $request)
    {
        $this->autorizarExportacaoDeAlunos();
        $view = $request->query('view'); // ex.: relatorios.alunos.fichas
        $tipo = $request->query('tipo', Relatorios::TIPO_BULK_FICHA);

        $dataEncoded = $request->query('data');
        $data = $this->decodeData($dataEncoded);

        return Relatorios::gerarRelatorio(
            view: $view,
            data: $data,
            tipo: $tipo,
        );
    }

    /**
     * Helper interno para decodificar o data.
     *
     * - data vem como base64(JSON)
     * - Aqui decodifica, faz json_decode e garante array
     */
    protected function decodeData(?string $encoded): array
    {
        if (blank($encoded)) {
            return [];
        }

        try {
            $json = base64_decode($encoded, true);

            if ($json === false) {
                return [];
            }

            $data = json_decode($json, true);

            if (! is_array($data)) {
                return [];
            }

            return $data;
        } catch (\Throwable $e) {
            // Se der ruim, volta array vazio para nao quebrar.
            return [];
        }
    }

    protected function autorizarExportacaoDeAlunos(): void
    {
        abort_unless(Auth::user()?->hasPermissionLike('exportar relatorio de alunos'), 403);
    }
}

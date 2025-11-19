<?php

namespace App\Http\Controllers;

use App\Relatorios\Relatorios;
use Illuminate\Http\Request;

class RelatorioController extends Controller
{
    /**
     * Ficha de um único objeto (modelo).
     * Espera: view, data (JSON base64), tipo=ficha
     */
    public function ficha(Request $request)
    {
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
     * Lista (bulkList) de vários objetos.
     */
    public function bulkList(Request $request)
    {
        // Recupera o pacote salvo na sessão
        $payload = $request->session()->get('relatorios.bulk_list');

        if (! $payload) {
            abort(400, 'Nenhum dado encontrado para gerar o relatório.');
        }

        $view = $payload['view'] ?? null;
        $data = $payload['data'] ?? [];
        $tipo = $payload['tipo'] ?? Relatorios::TIPO_BULK_LIST;

        if (! $view) {
            abort(400, 'View do relatório não informada.');
        }

        // Aqui você chama sua classe geradora de PDF
        // Ajuste o método conforme sua implementação atual:
        // Exemplo:
        return Relatorios::gerarRelatorio(
            view: $view,
            data: $data,
            tipo: $tipo,
        );

        // ou, se for um método de instância:
        // $rel = new Relatorios();
        // return $rel->gerarRelatorio($view, $data, $tipo);
    }

    /**
     * Fichas em lote (bulkFicha) — várias fichas detalhadas.
     */
    public function bulkFicha(Request $request)
    {
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
            // se der ruim, volta array vazio pra não quebrar
            return [];
        }
    }
}

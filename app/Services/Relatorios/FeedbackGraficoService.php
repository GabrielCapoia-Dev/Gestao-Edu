<?php

namespace App\Services\Relatorios;

use App\Models\FeedbackPedido;
use Illuminate\Database\Eloquent\Collection;

class FeedbackGraficoService
{
    /**
     * Gera dados para o gráfico de média mensal
     */
    public function gerarDadosMediaMensal(array $filtros = []): array
    {
        $query = FeedbackPedido::query();

        // Aplicar mesmos filtros dos widgets
        $this->aplicarFiltros($query, $filtros);

        $dados = $query
            ->selectRaw("
                DATE_FORMAT(created_at, '%Y-%m') as mes,
                COUNT(*) as total
            ")
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes');

        if ($dados->isEmpty()) {
            return [
                'labels' => [],
                'data' => [],
            ];
        }

        return [
            'labels' => $dados->keys()->map(function ($mes) {
                [$ano, $mês] = explode('-', $mes);
                $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                return $meses[(int)$mês - 1] . '/' . substr($ano, 2);
            })->toArray(),
            'data' => $dados->values()->toArray(),
        ];
    }

    /**
     * Gera dados para o gráfico de quantidade por nota
     */
    public function gerarDadosPorNota(array $filtros = []): array
    {
        $query = FeedbackPedido::query();

        // Aplicar mesmos filtros dos widgets
        $this->aplicarFiltros($query, $filtros);

        $dados = $query
            ->selectRaw('valor, COUNT(*) as total')
            ->groupBy('valor')
            ->orderBy('valor')
            ->pluck('total', 'valor');

        // Garante que sempre existam 1..5
        $notas = [1, 2, 3, 4, 5];
        $valores = collect($notas)->map(fn($nota) => $dados[$nota] ?? 0);

        return [
            'labels' => $notas,
            'data' => $valores->toArray(),
        ];
    }

    /**
     * Renderiza gráfico como imagem PNG em base64
     * Usar isso via Node.js/canvas-renderizador
     */
    public function gerarChartConfig(string $tipo, array $dados): array
    {
        if ($tipo === 'media_mensal') {
            return [
                'type' => 'line',
                'data' => [
                    'labels' => $dados['labels'],
                    'datasets' => [
                        [
                            'label' => 'Quantidade',
                            'data' => $dados['data'],
                            'borderColor' => 'rgb(59, 130, 246)',
                            'backgroundColor' => 'rgba(7, 79, 155, 0.375)',
                            'borderWidth' => 3,
                            'fill' => true,
                            'tension' => 0.4,
                            'pointRadius' => 6,
                            'pointBackgroundColor' => 'rgb(59, 130, 246)',
                            'pointBorderColor' => '#fff',
                            'pointBorderWidth' => 2,
                            'pointHoverRadius' => 8,
                        ],
                    ],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => true,
                    'interaction' => [
                        'mode' => 'index',
                        'intersect' => false,
                    ],
                    'scales' => [
                        'x' => ['grid' => ['display' => false]],
                        'y' => ['beginAtZero' => true],
                    ],
                    'plugins' => [
                        'legend' => [
                            'display' => true,
                            'position' => 'top',
                        ],
                    ],
                ],
            ];
        }

        if ($tipo === 'por_nota') {
            return [
                'type' => 'bar',
                'data' => [
                    'labels' => array_map(fn($n) => "Nota $n", $dados['labels']),
                    'datasets' => [
                        [
                            'label' => 'Quantidade',
                            'data' => $dados['data'],
                            'backgroundColor' => [
                                '#ef4444',
                                '#f97316',
                                '#facc15',
                                '#84cc16',
                                '#22c55e',
                            ],
                            'borderRadius' => 8,
                        ],
                    ],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => true,
                    'plugins' => [
                        'legend' => [
                            'display' => false,
                        ],
                    ],
                    'scales' => [
                        'x' => ['grid' => ['display' => false]],
                        'y' => [
                            'beginAtZero' => true,
                            'ticks' => ['precision' => 0],
                        ],
                    ],
                ],
            ];
        }

        return [];
    }

    /**
     * Aplicar filtros na query (reutiliza lógica dos widgets)
     */
    private function aplicarFiltros($query, array $filtros): void
    {
        if ($valor = $filtros['valor'] ?? null) {
            $query->where('valor', $valor);
        }

        if ($nivel = $filtros['nivel_prioridade'] ?? null) {
            $query->whereHas('pedido', fn($q) => $q->where('nivel_prioridade', $nivel));
        }

        if ($tipoManutencao = $filtros['tipo_manutencao_id'] ?? null) {
            $query->whereHas('pedido', fn($q) => $q->where('tipo_manutencao_id', $tipoManutencao));
        }

        if ($opcao = $filtros['tipo_manutencao_opcao_id'] ?? null) {
            $query->whereHas('itens.problema', fn($q) => $q->where('tipo_manutencao_opcao_id', $opcao));
        }

        if ($escola = $filtros['escola_id'] ?? null) {
            $query->whereHas('pedido', fn($q) => $q->where('escola_id', $escola));
        }

        if ($empresa = $filtros['empresa_contratada_id'] ?? null) {
            $query->whereHas('pedido', fn($q) => $q->where('empresa_contratada_id', $empresa));
        }

        if ($mes = $filtros['mes'] ?? null) {
            $query->whereMonth('created_at', $mes);
        }

        if ($periodo = $filtros['periodo'] ?? null) {
            if ($periodo['inicio'] ?? null) {
                $query->whereDate('created_at', '>=', $periodo['inicio']);
            }
            if ($periodo['fim'] ?? null) {
                $query->whereDate('created_at', '<=', $periodo['fim']);
            }
        }

        if ($inicio = $filtros['data_inicio'] ?? null) {
            $query->whereDate('created_at', '>=', $inicio);
        }

        if ($fim = $filtros['data_fim'] ?? null) {
            $query->whereDate('created_at', '<=', $fim);
        }

        if (array_key_exists('reabrir_pedido', $filtros)) {
            $query->where('reabrir_pedido', (bool) $filtros['reabrir_pedido']);
        }

        if ($resultado = $filtros['resultado'] ?? null) {
            $query->whereHas('itens', fn($q) => $q->where('resultado', $resultado));
        }
    }
}

<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\FeedbackPedido;
use Livewire\Attributes\On;

class FeedbackQuantidadePorNotaChart extends ChartWidget
{
    protected static ?string $heading = 'Quantidade de Avaliações por Nota';
    protected static bool $isLazy = false;

    public array $chartFilters = [];
    public bool $isLoading = false;

    #[On('update-chart-filters')]
    public function updateChartFilters(array $filters)
    {
        $this->chartFilters = $filters;
    }

    #[On('chart-loading-start')]
    public function startLoading()
    {
        $this->isLoading = true;
    }

    #[On('chart-loading-end')]
    public function endLoading()
    {
        $this->isLoading = false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public function getData(): array
    {
        if ($this->isLoading) {
            return [
                'datasets' => [
                    [
                        'label' => 'Carregando...',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $query = FeedbackPedido::query();

        // ===== Aplicação idêntica de filtros =====

        if ($valor = $this->chartFilters['valor'] ?? null) {
            $query->where('valor', $valor);
        }

        if ($nivel = $this->chartFilters['nivel_prioridade'] ?? null) {
            $query->whereHas('pedido', fn($q) => $q->where('nivel_prioridade', $nivel));
        }

        if ($tipoManutencao = $this->chartFilters['tipo_manutencao_id'] ?? null) {
            $query->whereHas('pedido', fn($q) => $q->where('tipo_manutencao_id', $tipoManutencao));
        }

        if ($escola = $this->chartFilters['escola_id'] ?? null) {
            $query->whereHas('pedido', fn($q) => $q->where('escola_id', $escola));
        }

        if ($empresa = $this->chartFilters['empresa_contratada_id'] ?? null) {
            $query->whereHas('pedido', fn($q) => $q->where('empresa_contratada_id', $empresa));
        }

        if ($mes = $this->chartFilters['mes'] ?? null) {
            $query->whereMonth('created_at', $mes);
        }

        if ($periodo = $this->chartFilters['periodo'] ?? null) {
            if ($periodo['inicio'] ?? null) {
                $query->whereDate('created_at', '>=', $periodo['inicio']);
            }
            if ($periodo['fim'] ?? null) {
                $query->whereDate('created_at', '<=', $periodo['fim']);
            }
        }

        // ===== AGREGAÇÃO DIFERENTE =====

        $dados = $query
            ->selectRaw('valor, COUNT(*) as total')
            ->groupBy('valor')
            ->orderBy('valor')
            ->pluck('total', 'valor');

        // Garante que sempre existam 1..5
        $notas = [1, 2, 3, 4, 5];

        $valores = collect($notas)->map(
            fn($nota) => $dados[$nota] ?? 0
        );

        return [
            'datasets' => [
                [
                    'label' => 'Quantidade',
                    'data' => $valores->toArray(),
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
            'labels' => $notas,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => true,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
            'animation' => [
                'duration' => 1200,
                'easing' => 'easeOutQuart',
            ],
        ];
    }
}
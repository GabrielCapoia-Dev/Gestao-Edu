<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\FeedbackPedido;
use Livewire\Attributes\On;

class FeedbackMediaMensalChart extends ChartWidget
{
    protected ?string $heading = 'Quantidade Mensal de Pedidos Concluídos';
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
        return 'line';
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

        // ===== FILTROS =====

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

        // ===== AGREGAÇÃO ALTERADA =====

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
                'datasets' => [
                    [
                        'label' => 'Quantidade',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Quantidade',
                    'data' => $dados->toArray(),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => '#074f9b60',
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
            'labels' => $dados->keys()->toArray(),
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
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => [
                    'beginAtZero' => true,
                    // removido max 5
                ],
            ],
            'animation' => [
                'duration' => 1200,
                'easing' => 'easeOutQuart',
            ],
        ];
    }
}

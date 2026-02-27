<?php

namespace App\Filament\Widgets;

use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use App\Models\FeedbackPedido;

class FeedbackDistribuicaoChart extends ApexChartWidget
{
    /**
     * Chart Id
     *
     * @var string
     */
    protected static ?string $chartId = 'feedbackDistribuicaoChart';

    /**
     * Widget Title
     *
     * @var string|null
     */
    protected static ?string $heading = 'Distribuição das Avaliações';

    /**
     * Chart options (series, labels, types, size, animations...)
     * https://apexcharts.com/docs/options
     *
     * @return array
     */
    protected function getOptions(): array
    {
        $dados = FeedbackPedido::selectRaw('valor, COUNT(*) as total')
            ->groupBy('valor')
            ->orderBy('valor')
            ->pluck('total', 'valor');

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 350,
            ],
            'series' => [
                [
                    'name' => 'Quantidade',
                    'data' => $dados->values(),
                ],
            ],
            'xaxis' => [
                'categories' => $dados->keys(),
            ],
            'colors' => ['#6366f1'],
        ];
    }
}

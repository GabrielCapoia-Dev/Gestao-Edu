<?php

namespace App\Filament\Widgets;

use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use App\Models\FeedbackPedido;

class FeedbackMediaMensalChart extends ApexChartWidget
{
    protected static ?string $chartId = 'feedbackMediaMensalChart';
    protected static ?string $heading = 'Média Mensal';

    protected function getOptions(): array
    {
        $dados = FeedbackPedido::selectRaw("
                DATE_FORMAT(created_at, '%Y-%m') as mes,
                AVG(valor) as media
            ")
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('media', 'mes');

        return [
            'chart' => [
                'type' => 'line',
                'height' => 350,
            ],
            'series' => [
                [
                    'name' => 'Média',
                    'data' => $dados->values()->map(fn ($v) => round($v, 2)),
                ],
            ],
            'xaxis' => [
                'categories' => $dados->keys(),
            ],
            'stroke' => [
                'curve' => 'smooth',
            ],
            'colors' => ['#10b981'],
        ];
    }
}
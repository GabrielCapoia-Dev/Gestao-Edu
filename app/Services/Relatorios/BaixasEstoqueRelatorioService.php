<?php

namespace App\Services\Relatorios;

use App\Models\BaixasEstoques;
use App\Models\Enums\MotivoBaixa;
use App\Models\Enums\TipoItem;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class BaixasEstoqueRelatorioService
{
    public function gerar(array $params, ?User $usuario): Response
    {
        $filtros = $this->extrairFiltros($params);
        $baixas = $this->buscarBaixas($filtros);

        $pdf = Pdf::loadView('relatorios.Estoque.baixas-estoque', [
            'baixas' => $baixas,
            'metricas' => $this->calcularMetricas($baixas),
            'filtros' => $this->formatarFiltros($filtros),
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
        ])->setPaper('a4', 'portrait')
            ->setOptions([
                'dpi' => 96,
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'isFontSubsettingEnabled' => true,
            ]);

        return $pdf->download('relatorio-baixas-estoque-' . now()->format('Y-m-d_H-i') . '.pdf');
    }

    protected function extrairFiltros(array $params): array
    {
        return [
            'categoria' => data_get($params, 'tableFilters.categoria.value'),
            'motivo' => data_get($params, 'tableFilters.motivo.value'),
        ];
    }

    protected function buscarBaixas(array $filtros): Collection
    {
        $query = BaixasEstoques::query()
            ->with('estoque.item')
            ->orderByDesc('created_at');

        if (! empty($filtros['categoria'])) {
            $query->whereHas('estoque.item', fn($q) => $q->where('tipo_item', $filtros['categoria']));
        }

        if (! empty($filtros['motivo'])) {
            $query->where('motivo', $filtros['motivo']);
        }

        return $query->get();
    }

    protected function calcularMetricas(Collection $baixas): object
    {
        $motivoMaisFrequente = $baixas->groupBy(fn($baixa) => $baixa->motivo?->value)
            ->sortByDesc(fn($grupo) => $grupo->count())
            ->keys()
            ->first();

        return (object) [
            'total_registros' => $baixas->count(),
            'quantidade_total' => round((float) $baixas->sum('quantidade'), 3),
            'itens_afetados' => $baixas->pluck('estoque.item_id')->filter()->unique()->count(),
            'motivo_mais_frequente' => $motivoMaisFrequente
                ? MotivoBaixa::from($motivoMaisFrequente)->label()
                : 'N/A',
        ];
    }

    protected function formatarFiltros(array $filtros): array
    {
        $resultado = [];

        if (! empty($filtros['categoria'])) {
            $resultado['categoria'] = TipoItem::from($filtros['categoria'])->label();
        }

        if (! empty($filtros['motivo'])) {
            $resultado['motivo'] = MotivoBaixa::from($filtros['motivo'])->label();
        }

        return $resultado;
    }
}

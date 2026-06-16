<?php

namespace App\Services\Relatorios;

use App\Models\InventarioRomaneio;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InventarioRomaneioRelatorioService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
    ) {}

    public function gerarPdf(InventarioRomaneio $romaneio, ?User $usuario): Response
    {
        $romaneio->loadMissing([
            'geradoPor',
            'pedidos.escola',
            'pedidos.itens.item',
        ]);

        $totais = $romaneio->pedidos
            ->flatMap(fn ($pedido) => $pedido->itens)
            ->groupBy('item_id')
            ->map(function (Collection $itens): array {
                $primeiro = $itens->first();

                return [
                    'item_nome' => $primeiro?->item?->nome ?? 'Item',
                    'unidade' => strtoupper($primeiro?->item?->unidade_medida?->value ?? 'N/A'),
                    'quantidade_total' => round((float) $itens->sum('quantidade_aprovada'), 3),
                ];
            })
            ->sortBy('item_nome', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $this->renderer->download('relatorios.Inventario.romaneio', [
            'romaneio' => $romaneio,
            'totais' => $totais,
            'reportTitle' => 'Romaneio de Inventários',
            'reportSubtitle' => 'Separação consolidada dos pedidos aprovados',
            'reportFilters' => [
                'romaneio' => $romaneio->codigo ?? 'N/A',
                'gerado_em' => $romaneio->gerado_em?->format('d/m/Y H:i') ?? 'N/A',
                'pedidos' => $romaneio->pedidos->count(),
            ],
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'orientation' => 'portrait',
        ], 'romaneio-' . mb_strtolower((string) ($romaneio->codigo ?? $romaneio->id)) . '.pdf');
    }
}

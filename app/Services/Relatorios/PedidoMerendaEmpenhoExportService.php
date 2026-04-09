<?php

namespace App\Services\Relatorios;

use App\Models\PedidoMerenda;
use App\Models\PedidoMerendaItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class PedidoMerendaEmpenhoExportService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
    ) {}

    public function exportar(PedidoMerenda $pedido, ?User $usuario): Response
    {
        $pedido->loadMissing([
            'itens.contratoItem.item',
            'itens.contratoItem.contrato.empresaContratada',
        ]);

        $gruposEmpresa = $this->agruparPorEmpresa($pedido);

        return $this->renderer->download('relatorios.PedidosMerenda.empenho', [
            'pedido' => $pedido,
            'gruposEmpresa' => $gruposEmpresa,
            'reportTitle' => 'Empenho de Pedido de Merenda',
            'reportSubtitle' => 'Pedido #' . $pedido->id,
            'reportFilters' => [
                'status' => $pedido->status?->label() ?? '-',
                'criado em' => $pedido->created_at?->format('d/m/Y H:i') ?? '-',
                'criado por' => $pedido->criado_por ?: '-',
                'empresas' => $gruposEmpresa->count(),
            ],
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'orientation' => 'portrait',
        ], 'empenho-pedido-merenda-' . $pedido->id . '.pdf');
    }

    protected function agruparPorEmpresa(PedidoMerenda $pedido): Collection
    {
        return $pedido->itens
            ->groupBy(function (PedidoMerendaItem $item) {
                $empresa = $item->contratoItem?->contrato?->empresaContratada;

                return $empresa?->getKey() ?: 'sem-empresa';
            })
            ->map(function (Collection $itens) {
                /** @var PedidoMerendaItem|null $primeiroItem */
                $primeiroItem = $itens->first();
                $empresa = $primeiroItem?->contratoItem?->contrato?->empresaContratada;

                $contratos = $itens
                    ->map(fn (PedidoMerendaItem $item) => $item->contratoItem?->contrato)
                    ->filter()
                    ->unique(fn ($contrato) => $contrato->getKey())
                    ->sortBy('numero_contrato')
                    ->values();

                return [
                    'empresa' => $empresa,
                    'contratos' => $contratos,
                    'itens' => $itens
                        ->sortBy(fn (PedidoMerendaItem $item) => ($item->contratoItem?->contrato?->numero_contrato ?? '') . '|' . ($item->contratoItem?->item?->nome ?? ''))
                        ->values(),
                    'quantidade_pedida' => (float) $itens->sum('quantidade_pedida'),
                    'quantidade_entregue' => (float) $itens->sum('quantidade_entregue'),
                    'quantidade_pendente' => (float) $itens->sum(fn (PedidoMerendaItem $item) => $item->quantidade_pendente),
                ];
            })
            ->values();
    }
}

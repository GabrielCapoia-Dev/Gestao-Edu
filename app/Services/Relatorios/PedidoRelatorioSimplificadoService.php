<?php

namespace App\Services\Relatorios;

use App\Models\Pedido;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PedidoRelatorioSimplificadoService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
        protected PedidoService $pedidoService,
    ) {}

    /**
     * @param  array<int|string>  $pedidoIds
     */
    public function gerar(array $pedidoIds, User $usuario): Response
    {
        $ids = collect($pedidoIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw new RuntimeException('Nenhum pedido foi selecionado para exportacao.');
        }

        $pedidos = $this->buscarPedidosSelecionados($ids, $usuario);

        if ($pedidos->isEmpty()) {
            throw new RuntimeException('Nenhum dos pedidos selecionados esta disponivel para exportacao.');
        }

        return $this->renderer->download('relatorios.Manutencao.pedidos-simplificado', [
            'pedidos' => $pedidos,
            'reportTitle' => 'Relatorio Simplificado de Manutencao',
            'reportSubtitle' => $pedidos->count().' pedido(s) selecionado(s)',
            'usuarioExportacao' => $usuario,
            'dataExportacao' => Carbon::now(),
            'showPagination' => false,
        ], 'pedidos-selecionados-'.now()->format('Y-m-d_H-i').'.pdf');
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Pedido>
     */
    protected function buscarPedidosSelecionados(Collection $ids, User $usuario): Collection
    {
        $query = Pedido::query()
            ->whereIn('id', $ids->all())
            ->where('ativo', true)
            ->with([
                'tipoManutencao',
                'tipoStatus',
                'escola',
                'setor',
                'empresaContratada',
                'solicitante',
                'fotos.usuario',
            ]);

        $this->pedidoService->aplicarEscopoConsulta($query, $usuario);

        $ordem = $ids->flip();

        return $query
            ->get()
            ->sortBy(fn (Pedido $pedido): int => (int) ($ordem[(int) $pedido->id] ?? PHP_INT_MAX))
            ->values();
    }
}

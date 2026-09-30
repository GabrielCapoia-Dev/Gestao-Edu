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
            throw new RuntimeException('Nenhum pedido foi selecionado para exportação.');
        }

        $pedidos = $this->buscarPedidosSelecionados($ids, $usuario);

        if ($pedidos->isEmpty()) {
            throw new RuntimeException('Nenhum dos pedidos selecionados está disponível para exportação.');
        }

        return $this->renderer->download('relatorios.Manutencao.pedidos-simplificado', [
            'pedidos' => $pedidos,
            'reportTitle' => 'Relatório Simplificado de Manutenção',
            'reportSubtitle' => $pedidos->count().' pedido(s) selecionado(s)',
            'usuarioExportacao' => $usuario,
            'dataExportacao' => Carbon::now(),
            'showPagination' => false,
        ], 'pedidos-selecionados-'.now()->format('Y-m-d_H-i').'.pdf');
    }

    /**
     * Divide a seleção por mês e por quantidade máxima de pedidos por PDF.
     * A consulta contém somente id e data; os relacionamentos pesados são
     * carregados apenas para a parte que será renderizada.
     *
     * @param  array<int|string>  $pedidoIds
     * @return array<int, array{periodo: string, ids: array<int, int>>>
     */
    public function particionarSelecionados(array $pedidoIds, User $usuario, int $tamanhoParte): array
    {
        $ids = collect($pedidoIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $ordem = $ids->flip();
        $query = Pedido::query()
            ->where('ativo', true)
            ->select(['id', 'data_solicitacao']);

        $this->pedidoService->aplicarEscopoConsulta($query, $usuario);

        $porMes = [];
        $selectionChunkSize = 1000;
        $databaseChunkSize = max(100, (int) config('exports.pdf_chunk_size', 100));

        foreach ($ids->chunk($selectionChunkSize) as $selection) {
            $chunkQuery = (clone $query)->whereIn('id', $selection->all());

            $chunkQuery->chunkById($databaseChunkSize, function (Collection $pedidos) use (&$porMes): void {
                foreach ($pedidos as $pedido) {
                    $periodo = $pedido->data_solicitacao
                        ? Carbon::parse($pedido->data_solicitacao)->format('Y-m')
                        : 'sem-data';

                    $porMes[$periodo][] = (int) $pedido->id;
                }
            }, 'id', 'id');
        }

        foreach ($porMes as &$periodIds) {
            usort(
                $periodIds,
                fn (int $left, int $right): int => (int) ($ordem[$left] ?? PHP_INT_MAX) <=> (int) ($ordem[$right] ?? PHP_INT_MAX),
            );
        }
        unset($periodIds);

        $tamanhoParte = max(1, $tamanhoParte);
        $partes = [];

        foreach ($porMes as $mes => $periodIds) {
            foreach (array_chunk($periodIds, $tamanhoParte) as $parte) {
                $partes[] = [
                    'periodo' => (string) $mes,
                    'ids' => array_map(static fn ($id): int => (int) $id, $parte),
                ];
            }
        }

        return $partes;
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

<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Exceptions\Exports\ExportPermanentException;
use App\Models\ExportRequest;
use App\Services\Exports\ChunkedPdfExportService;
use App\Services\Exports\ExportFileResult;
use App\Services\Relatorios\PedidoRelatorioGeralService;
use Illuminate\Support\Carbon;

class PedidoRelatorioListagemExportHandler implements ExportHandler
{
    public function __construct(
        private readonly PedidoRelatorioGeralService $service,
        private readonly ChunkedPdfExportService $chunkedPdf,
    ) {}

    public function handle(ExportRequest $exportRequest): ExportFileResult
    {
        $user = $exportRequest->user;

        if (! $user) {
            throw new ExportPermanentException('Usuário da exportação não encontrado.');
        }

        $filters = $exportRequest->filters ?? [];
        $query = $this->service->queryListagem($filters, $user);
        $total = (clone $query)->count();
        $reportFilters = $this->service->formatarFiltros($filters);

        $exportRequest->updateProgress(10, 100, "Preparando {$total} pedidos para listagem.");

        return $this->chunkedPdf->export(
            exportRequest: $exportRequest,
            query: $query,
            view: 'relatorios.Manutencao.listagem-pedidos',
            mapper: fn (object $pedido): array => $this->service->mapPedidoListagem($pedido),
            viewData: fn (array $pedidos): array => [
                'pedidos' => $pedidos,
                'totalPedidos' => $total,
                'filtros' => $reportFilters,
                'reportFilters' => $reportFilters,
                'reportTitle' => 'Listagem de Pedidos de Manutenção',
                'reportSubtitle' => 'Registros detalhados conforme os filtros selecionados',
                'usuarioExportacao' => $user,
                'dataExportacao' => Carbon::now(),
            ],
            fileName: 'listagem-pedidos.pdf',
            zipFileName: 'listagem-pedidos-partes.zip',
            chunkIdColumn: 'p.id',
        );
    }
}

<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Models\ExportRequest;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use App\Services\Relatorios\PedidoRelatorioSimplificadoService;
use RuntimeException;

class PedidoRelatorioSimplificadoExportHandler implements ExportHandler
{
    public function __construct(
        private readonly PedidoRelatorioSimplificadoService $service,
        private readonly ExportFileStorage $storage,
    ) {}

    public function handle(ExportRequest $exportRequest): ExportFileResult
    {
        $user = $exportRequest->user;

        if (! $user) {
            throw new RuntimeException('Usuario da exportacao nao encontrado.');
        }

        $pedidoIds = $exportRequest->filters['pedido_ids'] ?? [];

        if (! is_array($pedidoIds)) {
            throw new RuntimeException('A exportacao nao recebeu uma lista valida de pedidos.');
        }

        $exportRequest->updateProgress(10, 100, 'Preparando pedidos selecionados.');

        $response = $this->service->gerar($pedidoIds, $user);

        $exportRequest->updateProgress(90, 100, 'Salvando PDF em armazenamento privado.');

        return $this->storage->storeResponse(
            $exportRequest,
            $response,
            'pedidos-selecionados.pdf',
            'application/pdf',
        );
    }
}

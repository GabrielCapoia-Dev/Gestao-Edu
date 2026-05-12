<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Models\ExportRequest;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use App\Services\Relatorios\PedidoRelatorioGeralService;
use RuntimeException;

class PedidoRelatorioGeralExportHandler implements ExportHandler
{
    public function __construct(
        private readonly PedidoRelatorioGeralService $service,
        private readonly ExportFileStorage $storage,
    ) {}

    public function handle(ExportRequest $exportRequest): ExportFileResult
    {
        $user = $exportRequest->user;

        if (! $user) {
            throw new RuntimeException('Usuario da exportacao nao encontrado.');
        }

        $exportRequest->updateProgress(10, 100, 'Preparando consulta de pedidos.');

        $response = $this->service->gerar($exportRequest->filters ?? [], $user);

        $exportRequest->updateProgress(90, 100, 'Salvando PDF em armazenamento privado.');

        return $this->storage->storeResponse(
            $exportRequest,
            $response,
            'relatorio-geral-pedidos.pdf',
            'application/pdf',
        );
    }
}

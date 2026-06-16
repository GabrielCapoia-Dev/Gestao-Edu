<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Models\ExportRequest;
use App\Services\Avaliacoes\AvaliacaoDocumentoExportService;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use RuntimeException;

class AvaliacaoDocumentoExportHandler implements ExportHandler
{
    public function __construct(
        private readonly AvaliacaoDocumentoExportService $service,
        private readonly ExportFileStorage $storage,
    ) {}

    public function handle(ExportRequest $exportRequest): ExportFileResult
    {
        $user = $exportRequest->user;

        if (! $user) {
            throw new RuntimeException('Usuário da exportação não encontrado.');
        }

        $params = $exportRequest->filters ?? [];
        $format = strtolower((string) $exportRequest->format);

        $exportRequest->updateProgress(10, 100, 'Preparando documentos de avaliação.');

        $response = match ($format) {
            'csv' => $this->service->exportarCsv($params, $user),
            'pdf' => $this->service->exportar($params, $user),
            default => throw new RuntimeException("Formato de avaliação não suportado: {$format}."),
        };

        $exportRequest->updateProgress(90, 100, 'Salvando documento em armazenamento privado.');

        return $this->storage->storeResponse(
            $exportRequest,
            $response,
            'avaliação-documento.' . $format,
            $format === 'csv' ? 'text/csv' : 'application/pdf',
        );
    }
}

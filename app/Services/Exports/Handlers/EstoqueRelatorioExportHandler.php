<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Models\Estoque;
use App\Models\ExportRequest;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use App\Services\Relatorios\EstoqueRelatorioService;
use RuntimeException;

class EstoqueRelatorioExportHandler implements ExportHandler
{
    public function __construct(
        private readonly EstoqueRelatorioService $service,
        private readonly ExportFileStorage $storage,
    ) {}

    public function handle(ExportRequest $exportRequest): ExportFileResult
    {
        $user = $exportRequest->user;

        if (! $user) {
            throw new RuntimeException('Usuário da exportação não encontrado.');
        }

        $format = strtolower((string) $exportRequest->format);
        $filters = $exportRequest->filters ?? [];

        $exportRequest->updateProgress(10, 100, 'Preparando dados de estoque.');

        $response = match ($exportRequest->type) {
            'estoque_geral' => match ($format) {
                'pdf' => $this->service->gerarPdfGeral($filters, $user),
                'xlsx' => $this->service->gerarXlsxGeral($filters, $user),
                default => throw new RuntimeException("Formato de estoque não suportado: {$format}."),
            },
            'estoque_item' => $this->handleItem($filters, $format, $user),
            default => throw new RuntimeException("Tipo de estoque não suportado: {$exportRequest->type}."),
        };

        $exportRequest->updateProgress(90, 100, 'Salvando relatório de estoque.');

        return $this->storage->storeResponse(
            $exportRequest,
            $response,
            'relatório-estoque.' . $format,
            $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/pdf',
        );
    }

    private function handleItem(array $filters, string $format, mixed $user)
    {
        $estoque = Estoque::query()->findOrFail((int) ($filters['estoque_id'] ?? 0));

        return match ($format) {
            'pdf' => $this->service->gerarPdfItem($estoque, $user),
            'xlsx' => $this->service->gerarXlsxItem($estoque, $user),
            default => throw new RuntimeException("Formato de estoque não suportado: {$format}."),
        };
    }
}

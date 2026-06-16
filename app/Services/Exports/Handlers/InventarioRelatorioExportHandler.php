<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Models\ExportRequest;
use App\Models\Inventario;
use App\Models\InventarioEstoque;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use App\Services\Relatorios\InventarioRelatorioService;
use RuntimeException;

class InventarioRelatorioExportHandler implements ExportHandler
{
    public function __construct(
        private readonly InventarioRelatorioService $service,
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

        $exportRequest->updateProgress(10, 100, 'Preparando dados de inventário.');

        $response = match ($exportRequest->type) {
            'inventario_geral' => $this->handleGeral($filters, $format, $user),
            'inventario_rede' => $this->handleRede($filters, $format, $user),
            'inventario_item' => $this->handleItem($filters, $format, $user),
            default => throw new RuntimeException("Tipo de inventário não suportado: {$exportRequest->type}."),
        };

        $exportRequest->updateProgress(90, 100, 'Salvando relatório de inventário.');

        return $this->storage->storeResponse(
            $exportRequest,
            $response,
            'relatório-inventário.' . $format,
            $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/pdf',
        );
    }

    private function handleGeral(array $filters, string $format, mixed $user)
    {
        $inventario = Inventario::query()->findOrFail((int) ($filters['inventario_id'] ?? 0));

        return match ($format) {
            'pdf' => $this->service->gerarPdfGeral($inventario, $filters, $user),
            'xlsx' => $this->service->gerarXlsxGeral($inventario, $filters, $user),
            default => throw new RuntimeException("Formato de inventário não suportado: {$format}."),
        };
    }

    private function handleRede(array $filters, string $format, mixed $user)
    {
        return match ($format) {
            'pdf' => $this->service->gerarPdfEnviosEscolas($filters, $user),
            'xlsx' => $this->service->gerarXlsxEnviosEscolas($filters, $user),
            default => throw new RuntimeException("Formato de inventário não suportado: {$format}."),
        };
    }

    private function handleItem(array $filters, string $format, mixed $user)
    {
        $estoque = InventarioEstoque::query()->findOrFail((int) ($filters['inventario_estoque_id'] ?? 0));

        return match ($format) {
            'pdf' => $this->service->gerarPdfItem($estoque, $user),
            'xlsx' => $this->service->gerarXlsxItem($estoque, $user),
            default => throw new RuntimeException("Formato de inventário não suportado: {$format}."),
        };
    }
}

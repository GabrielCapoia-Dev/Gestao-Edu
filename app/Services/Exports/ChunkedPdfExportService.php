<?php

namespace App\Services\Exports;

use App\Exceptions\Exports\ExportCancelledException;
use App\Exceptions\Exports\ExportPermanentException;
use App\Models\ExportRequest;
use App\Services\Relatorios\RelatorioPdfRenderer;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use ZipArchive;

class ChunkedPdfExportService
{
    public function __construct(
        private readonly RelatorioPdfRenderer $renderer,
        private readonly ExportFileStorage $storage,
        private readonly ExportTemporaryFiles $temporaryFiles,
    ) {}

    /**
     * @param EloquentBuilder|QueryBuilder $query
     * @param callable(mixed): array<string, mixed> $mapper
     * @param callable(array<string, mixed>): array<string, mixed> $viewData
     */
    public function export(
        ExportRequest $exportRequest,
        EloquentBuilder|QueryBuilder $query,
        string $view,
        callable $mapper,
        callable $viewData,
        string $fileName,
        string $zipFileName,
        string $mime = 'application/pdf',
        string $chunkIdColumn = 'id',
    ): ExportFileResult {
        $temporaryDirectory = $this->temporaryFiles->begin($exportRequest);
        $partPaths = [];
        $part = 0;
        $total = max(1, (int) (clone $query)->count());
        $processed = 0;
        $chunkSize = max(1, (int) config('exports.pdf_chunk_size', 100));

        try {
            $query->chunkById($chunkSize, function (Collection $rows) use (
                $exportRequest,
                $view,
                $mapper,
                $viewData,
                $temporaryDirectory,
                &$partPaths,
                &$part,
                &$processed,
                $total,
            ): void {
                $this->assertActive($exportRequest);
                $part++;
                $jsonPath = $this->temporaryFiles->path($temporaryDirectory, sprintf('part-%04d.jsonl', $part));
                $compiled = $rows->map($mapper)->all();

                $this->temporaryFiles->writeJsonLines($jsonPath, $compiled);
                $compiled = $this->temporaryFiles->readJsonLines($jsonPath);
                $pdfPath = $this->temporaryFiles->path($temporaryDirectory, sprintf('part-%04d.pdf', $part));

                $this->renderer->renderToFile(
                    $view,
                    $viewData($compiled),
                    $pdfPath,
                );

                $partPaths[] = $pdfPath;
                $processed += count($compiled);
                $progress = min(90, 10 + (int) floor(($processed / $total) * 80));
                $exportRequest->updateProgress($progress, 100, "Gerando parte {$part} do relatório.");
            }, $chunkIdColumn, 'id');

            if ($part === 0) {
                $part = 1;
                $pdfPath = $this->temporaryFiles->path($temporaryDirectory, 'part-0001.pdf');
                $this->renderer->renderToFile($view, $viewData([]), $pdfPath);
                $partPaths[] = $pdfPath;
            }

            $this->assertActive($exportRequest);
            $exportRequest->updateProgress(92, 100, 'Salvando arquivo em armazenamento privado.');

            if (count($partPaths) === 1) {
                return $this->storage->storeFile($exportRequest, $partPaths[0], $fileName, $mime);
            }

            $zipPath = $this->temporaryFiles->path($temporaryDirectory, $zipFileName);

            if (! class_exists(ZipArchive::class)) {
                throw new ExportPermanentException('O servidor não possui suporte a arquivos ZIP para dividir este relatório.');
            }

            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Não foi possível criar o arquivo compactado da exportação.');
            }

            foreach ($partPaths as $index => $partPath) {
                $zip->addFile($partPath, sprintf('%s-parte-%02d.pdf', pathinfo($fileName, PATHINFO_FILENAME), $index + 1));
            }

            $zip->close();

            return $this->storage->storeFile($exportRequest, $zipPath, $zipFileName, 'application/zip');
        } finally {
            $this->temporaryFiles->cleanup($temporaryDirectory);
        }
    }

    private function assertActive(ExportRequest $exportRequest): void
    {
        $fresh = $exportRequest->refresh();

        if (! $fresh || ! in_array($fresh->status, [ExportRequest::STATUS_QUEUED, ExportRequest::STATUS_RUNNING], true) || $fresh->cancel_requested_at) {
            throw new ExportCancelledException('A exportação foi cancelada pelo usuário.');
        }
    }
}

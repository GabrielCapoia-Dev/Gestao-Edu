<?php

namespace App\Services\Exports\Handlers;

use App\Contracts\Exports\ExportHandler;
use App\Exceptions\Exports\ExportCancelledException;
use App\Exceptions\Exports\ExportPermanentException;
use App\Models\ExportRequest;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportFileStorage;
use App\Services\Exports\ExportTemporaryFiles;
use App\Services\Relatorios\PedidoRelatorioSimplificadoService;
use RuntimeException;
use ZipArchive;

class PedidoRelatorioSimplificadoExportHandler implements ExportHandler
{
    public function __construct(
        private readonly PedidoRelatorioSimplificadoService $service,
        private readonly ExportFileStorage $storage,
        private readonly ExportTemporaryFiles $temporaryFiles,
    ) {}

    public function handle(ExportRequest $exportRequest): ExportFileResult
    {
        $user = $exportRequest->user;

        if (! $user) {
            throw new RuntimeException('Usuário da exportação não encontrado.');
        }

        $pedidoIds = $exportRequest->filters['pedido_ids'] ?? [];

        if (! is_array($pedidoIds)) {
            throw new RuntimeException('A exportação não recebeu uma lista valida de pedidos.');
        }

        $partes = $this->service->particionarSelecionados(
            $pedidoIds,
            $user,
            (int) config('exports.simplified_pdf_part_size', 250),
        );

        if ($partes === []) {
            throw new ExportPermanentException('Nenhum dos pedidos selecionados está disponível para exportação.');
        }

        $exportRequest->updateProgress(10, 100, 'Preparando partes por período.');
        $temporaryDirectory = $this->temporaryFiles->begin($exportRequest);
        $partPaths = [];
        $processed = 0;
        $total = array_sum(array_map(static fn (array $part): int => count($part['ids']), $partes));

        try {
            foreach ($partes as $index => $parte) {
                $this->assertActive($exportRequest);
                $partNumber = $index + 1;
                try {
                    $response = $this->service->gerar($parte['ids'], $user);
                    $contents = (string) $response->getContent();
                    $pdfPath = $this->temporaryFiles->path($temporaryDirectory, sprintf('parte-%04d.pdf', $partNumber));

                    if ($contents === '' || file_put_contents($pdfPath, $contents) === false || (filesize($pdfPath) ?: 0) <= 0) {
                        throw new RuntimeException('Não foi possível gravar uma parte do relatório selecionado.');
                    }
                } finally {
                    unset($response, $contents);
                    gc_collect_cycles();
                }

                $partPaths[] = $pdfPath;
                $processed += count($parte['ids']);
                $progress = min(90, 10 + (int) floor(($processed / max(1, $total)) * 80));
                $exportRequest->updateProgress($progress, 100, "Gerando parte {$partNumber} de ".count($partes)." ({$parte['periodo']}).");
            }

            $this->assertActive($exportRequest);
            $exportRequest->updateProgress(92, 100, 'Salvando arquivo em armazenamento privado.');

            if (count($partPaths) === 1) {
                return $this->storage->storeFile(
                    $exportRequest,
                    $partPaths[0],
                    'pedidos-selecionados.pdf',
                    'application/pdf',
                );
            }

            if (! class_exists(ZipArchive::class)) {
                throw new ExportPermanentException('O servidor não possui suporte a arquivos ZIP para dividir este relatório.');
            }

            $zipFileName = 'pedidos-selecionados-partes.zip';
            $zipPath = $this->temporaryFiles->path($temporaryDirectory, $zipFileName);
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Não foi possível criar o arquivo compactado da exportação.');
            }

            $zipOpened = true;

            try {
                foreach ($partPaths as $index => $partPath) {
                    $periodo = preg_replace('/[^0-9a-z-]+/i', '-', (string) ($partes[$index]['periodo'] ?? 'sem-data')) ?: 'sem-data';

                    if (! $zip->addFile($partPath, sprintf('pedidos-selecionados-%s-parte-%02d.pdf', $periodo, $index + 1))) {
                        throw new RuntimeException('Não foi possível adicionar uma parte ao arquivo compactado da exportação.');
                    }
                }

                if (! $zip->close()) {
                    throw new RuntimeException('Não foi possível finalizar o arquivo compactado da exportação.');
                }

                $zipOpened = false;
            } finally {
                if ($zipOpened) {
                    $zip->close();
                }
            }

            if (! is_file($zipPath) || (filesize($zipPath) ?: 0) <= 0) {
                throw new RuntimeException('O arquivo compactado da exportação foi gerado vazio.');
            }

            $verification = new ZipArchive();
            $verificationOpened = $verification->open($zipPath);

            if ($verificationOpened !== true || $verification->numFiles !== count($partPaths)) {
                if ($verificationOpened === true) {
                    $verification->close();
                }

                throw new RuntimeException('O arquivo compactado da exportação não contém todas as partes esperadas.');
            }

            $verification->close();

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

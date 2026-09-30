<?php

namespace App\Services\Exports;

use App\Models\ExportRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportFileStorage
{
    public function storeResponse(
        ExportRequest $exportRequest,
        Response $response,
        string $fallbackFileName,
        ?string $fallbackMime = null,
    ): ExportFileResult {
        $contents = $this->responseContents($response);

        if ($contents === '') {
            throw new RuntimeException('O processamento terminou sem gerar conteudo para o arquivo.');
        }

        $fileName = $this->fileNameFromResponse($response) ?: $fallbackFileName;
        $mime = $response->headers->get('content-type') ?: $fallbackMime ?: 'application/octet-stream';

        return $this->storeContents($exportRequest, $contents, $fileName, $mime);
    }

    public function storeContents(
        ExportRequest $exportRequest,
        string $contents,
        string $fileName,
        ?string $mime = null,
    ): ExportFileResult {
        $disk = (string) config('exports.disk', 'local');
        $safeFileName = $this->sanitizeFileName($fileName);
        $path = sprintf(
            'exports/%s/%s/%s',
            now()->format('Y/m'),
            $exportRequest->getKey(),
            $safeFileName
        );

        Storage::disk($disk)->put($path, $contents);

        return new ExportFileResult(
            disk: $disk,
            path: $path,
            fileName: $safeFileName,
            mime: $mime,
            sizeBytes: strlen($contents),
            checksum: hash('sha256', $contents),
        );
    }

    public function storeFile(
        ExportRequest $exportRequest,
        string $sourcePath,
        string $fileName,
        ?string $mime = null,
    ): ExportFileResult {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException('O arquivo temporário da exportação não está disponível.');
        }

        $disk = (string) config('exports.disk', 'local');
        $safeFileName = $this->sanitizeFileName($fileName);
        $path = sprintf(
            'exports/%s/%s/%s',
            now()->format('Y/m'),
            $exportRequest->getKey(),
            $safeFileName
        );
        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo da exportação.');
        }

        try {
            if (! Storage::disk($disk)->put($path, $stream)) {
                throw new RuntimeException('Não foi possível armazenar o arquivo da exportação.');
            }
        } finally {
            fclose($stream);
        }

        return new ExportFileResult(
            disk: $disk,
            path: $path,
            fileName: $safeFileName,
            mime: $mime,
            sizeBytes: filesize($sourcePath) ?: 0,
            checksum: hash_file('sha256', $sourcePath) ?: null,
        );
    }

    private function responseContents(Response $response): string
    {
        if ($response instanceof StreamedResponse) {
            ob_start();
            $response->sendContent();

            return (string) ob_get_clean();
        }

        if ($response instanceof BinaryFileResponse) {
            $path = $response->getFile()->getPathname();
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException('Não foi possível ler o arquivo temporário gerado.');
            }

            if (str_starts_with($path, sys_get_temp_dir()) && is_file($path)) {
                @unlink($path);
            }

            return $contents;
        }

        return (string) $response->getContent();
    }

    private function fileNameFromResponse(Response $response): ?string
    {
        $disposition = (string) $response->headers->get('content-disposition');

        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $matches) !== 1) {
            return null;
        }

        return rawurldecode(trim($matches[1], "\"' "));
    }

    private function sanitizeFileName(string $fileName): string
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $baseName = Str::slug($baseName) ?: 'exportacao';

        return $extension !== ''
            ? "{$baseName}.{$extension}"
            : $baseName;
    }
}

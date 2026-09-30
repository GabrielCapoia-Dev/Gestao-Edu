<?php

namespace App\Services\Exports;

use App\Models\ExportRequest;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ExportTemporaryFiles
{
    private const ROOT = 'exports-tmp';

    public function begin(ExportRequest $exportRequest): string
    {
        return $this->withRequestLock((string) $exportRequest->getKey(), function () use ($exportRequest): string {
            $directory = $this->requestDirectory($exportRequest).'/'.Str::uuid();
            Storage::disk('local')->makeDirectory($directory);

            return $directory;
        });
    }

    public function path(string $directory, string $fileName): string
    {
        return Storage::disk('local')->path(trim($directory, '/').'/'.ltrim($fileName, '/'));
    }

    /** @param iterable<array<string, mixed>> $rows */
    public function writeJsonLines(string $path, iterable $rows): int
    {
        $handle = fopen($path, 'ab');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível preparar o arquivo temporário da exportação.');
        }

        $count = 0;

        try {
            foreach ($rows as $row) {
                $encoded = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                fwrite($handle, $encoded."\n");
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /** @return array<int, array<string, mixed>> */
    public function readJsonLines(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível ler o arquivo temporário da exportação.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $decoded = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    $rows[] = $decoded;
                }
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    public function cleanup(string $directory): void
    {
        $disk = Storage::disk('local');
        $disk->deleteDirectory($directory);
        $requestDirectory = dirname(trim($directory, '/'));
        $requestId = basename($requestDirectory);

        try {
            $this->withRequestLock($requestId, function () use ($disk, $requestDirectory): void {
                if ($disk->directories($requestDirectory) === [] && $disk->allFiles($requestDirectory) === []) {
                    $disk->deleteDirectory($requestDirectory);
                }
            });
        } catch (\Throwable) {
            // A tentativa já foi removida; o diretório-pai será recolhido na manutenção.
        }
    }

    public function pruneOrphans(?int $olderThanMinutes = null): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subMinutes(max(1, $olderThanMinutes ?? (int) config('exports.temporary_retention_minutes', 1440)));
        $removed = 0;

        foreach ($disk->directories(self::ROOT) as $requestDirectory) {
            $exportId = basename($requestDirectory);
            try {
                $this->withRequestLock($exportId, function () use ($disk, $requestDirectory, $exportId, $cutoff, &$removed): void {
                    $exportRequest = ExportRequest::query()->find($exportId);

                    if ($exportRequest?->isActive()) {
                        return;
                    }

                    foreach ($disk->directories($requestDirectory) as $directory) {
                        $files = $disk->allFiles($directory);
                        $lastModified = collect($files)->map(fn (string $file): int => $disk->lastModified($file))->max();

                        if ($lastModified === null) {
                            try {
                                $lastModified = $disk->lastModified($directory);
                            } catch (\Throwable) {
                                continue;
                            }
                        }

                        if ($lastModified !== null && now()->setTimestamp($lastModified)->lt($cutoff)) {
                            $disk->deleteDirectory($directory);
                            $removed++;
                        }
                    }

                    if ($disk->directories($requestDirectory) === [] && $disk->allFiles($requestDirectory) === []) {
                        $disk->deleteDirectory($requestDirectory);
                    }
                });
            } catch (\Throwable) {
                continue;
            }

        }

        return $removed;
    }

    private function requestDirectory(ExportRequest $exportRequest): string
    {
        return self::ROOT.'/'.$exportRequest->getKey();
    }

    private function withRequestLock(string $requestId, Closure $callback): mixed
    {
        return Cache::lock('exports:temporary-directory:'.$requestId, 30)
            ->block(5, $callback);
    }
}

<?php

namespace App\Services\Exports;

use App\Models\ExportRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StalledExportRequestMonitorService
{
    /**
     * @return array{checked:int,cancelled:int}
     */
    public function handle(?int $queuedMinutes = null, ?int $runningMinutes = null): array
    {
        $queuedMinutes = max(1, $queuedMinutes ?? (int) config('exports.stalled_queued_after_minutes', 30));
        $runningMinutes = max(1, $runningMinutes ?? (int) config('exports.stalled_running_after_minutes', 45));
        $queuedCutoff = now()->subMinutes($queuedMinutes);
        $runningCutoff = now()->subMinutes($runningMinutes);

        $ids = ExportRequest::query()
            ->where(function ($query) use ($queuedCutoff, $runningCutoff): void {
                $query
                    ->where(function ($queued) use ($queuedCutoff): void {
                        $queued
                            ->where('status', ExportRequest::STATUS_QUEUED)
                            ->where('updated_at', '<=', $queuedCutoff);
                    })
                    ->orWhere(function ($running) use ($runningCutoff): void {
                        $running
                            ->where('status', ExportRequest::STATUS_RUNNING)
                            ->where('updated_at', '<=', $runningCutoff);
                    });
            })
            ->orderBy('updated_at')
            ->pluck('id');

        $cancelled = 0;

        foreach ($ids as $id) {
            $wasCancelled = DB::transaction(function () use ($id, $queuedCutoff, $runningCutoff): bool {
                /** @var ExportRequest|null $exportRequest */
                $exportRequest = ExportRequest::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (! $exportRequest || ! $exportRequest->isActive()) {
                    return false;
                }

                $isStalled = match ($exportRequest->status) {
                    ExportRequest::STATUS_QUEUED => $exportRequest->updated_at?->lte($queuedCutoff) ?? false,
                    ExportRequest::STATUS_RUNNING => $exportRequest->updated_at?->lte($runningCutoff) ?? false,
                    default => false,
                };

                if (! $isStalled) {
                    return false;
                }

                $errorMessage = $this->buildErrorMessage($exportRequest);

                $exportRequest->markCancelled(
                    message: $this->buildStatusMessage($exportRequest),
                    errorMessage: $errorMessage,
                );

                $this->logFailedJob($exportRequest, $errorMessage);

                return true;
            });

            if ($wasCancelled) {
                $cancelled++;
            }
        }

        return [
            'checked' => $ids->count(),
            'cancelled' => $cancelled,
        ];
    }

    private function buildStatusMessage(ExportRequest $exportRequest): string
    {
        return $exportRequest->format === 'processo'
            ? 'Processo cancelado por inatividade na fila.'
            : 'Exportacao cancelada por inatividade na fila.';
    }

    private function buildErrorMessage(ExportRequest $exportRequest): string
    {
        $statusLabel = $exportRequest->status === ExportRequest::STATUS_RUNNING ? 'em processamento' : 'na fila';

        return sprintf(
            'Solicitacao cancelada automaticamente apos ficar parada %s. ExportRequest=%s, tipo=%s, formato=%s.',
            $statusLabel,
            $exportRequest->getKey(),
            $exportRequest->type,
            $exportRequest->format
        );
    }

    private function logFailedJob(ExportRequest $exportRequest, string $errorMessage): void
    {
        DB::table(config('queue.failed.table', 'failed_jobs'))->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => (string) config('queue.default', 'database'),
            'queue' => $this->resolveQueueName($exportRequest),
            'payload' => json_encode([
                'displayName' => self::class,
                'job' => self::class,
                'data' => [
                    'export_request_id' => $exportRequest->getKey(),
                    'type' => $exportRequest->type,
                    'format' => $exportRequest->format,
                    'status' => $exportRequest->status,
                    'label' => $exportRequest->label,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'exception' => $errorMessage,
            'failed_at' => now(),
        ]);
    }

    private function resolveQueueName(ExportRequest $exportRequest): string
    {
        return match ($exportRequest->metadata['process_kind'] ?? null) {
            'importacao_alunos', 'exclusao_alunos_massa' => (string) config('imports.queue', config('exports.queue', 'exports')),
            default => (string) config('exports.queue', 'exports'),
        };
    }
}

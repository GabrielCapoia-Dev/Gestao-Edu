<?php

namespace App\Services\Exports;

use App\Jobs\ProcessExportRequestJob;
use App\Models\ExportRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StalledExportRequestMonitorService
{
    /**
     * @return array{checked:int,requeued:int,failed:int,cancelled:int}
     */
    public function handle(?int $queuedMinutes = null, ?int $runningMinutes = null): array
    {
        $queuedMinutes = max(1, $queuedMinutes ?? (int) config('exports.stalled_queued_after_minutes', 30));
        $runningMinutes = max(1, $runningMinutes ?? (int) config('exports.stalled_running_after_minutes', 45));
        $queuedCutoff = now()->subMinutes($queuedMinutes);
        $runningCutoff = now()->subMinutes($runningMinutes);
        $legacyCancellationCutoff = now()->subDays((int) config('exports.expiration_days', 7));

        $candidates = ExportRequest::query()
            ->where(function ($query) use ($queuedCutoff, $runningCutoff, $legacyCancellationCutoff): void {
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
                    })
                    ->orWhere(function ($cancelled) use ($legacyCancellationCutoff): void {
                        $cancelled
                            ->where('status', ExportRequest::STATUS_CANCELLED)
                            ->where('format', '<>', 'processo')
                            ->whereIn('status_message', $this->automaticCancellationMessages())
                            ->where('finished_at', '>=', $legacyCancellationCutoff);
                    });
            });
        $results = [
            'checked' => 0,
            'requeued' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];

        $candidates->orderBy('id')->chunkById(100, function ($candidateBatch) use (&$results, $queuedCutoff, $runningCutoff): void {
            $ids = $candidateBatch->pluck('id');
            $actions = DB::transaction(function () use ($ids, $queuedCutoff, $runningCutoff): array {
                $requests = ExportRequest::query()
                    ->whereIn('id', $ids)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $actions = [];

                foreach ($ids as $id) {
                    $exportRequest = $requests->get($id);
                    if (! $exportRequest) {
                        continue;
                    }

                    if ($this->isAutomaticallyCancelledExport($exportRequest)) {
                        $action = $this->recoverExport($exportRequest);
                        $actions[] = $action;
                        continue;
                    }

                    if (! $exportRequest->isActive()) {
                        continue;
                    }

                    $isStalled = match ($exportRequest->status) {
                        ExportRequest::STATUS_QUEUED => $exportRequest->updated_at?->lte($queuedCutoff) ?? false,
                        ExportRequest::STATUS_RUNNING => $exportRequest->updated_at?->lte($runningCutoff) ?? false,
                        default => false,
                    };

                    if (! $isStalled) {
                        continue;
                    }

                    if ($exportRequest->format !== 'processo') {
                        $actions[] = $this->recoverExport($exportRequest);
                        continue;
                    }

                    $errorMessage = $this->buildErrorMessage($exportRequest);

                    $exportRequest->markCancelled(
                        message: $this->buildStatusMessage($exportRequest),
                        errorMessage: $errorMessage,
                    );

                    $this->logFailedJob($exportRequest, $errorMessage);

                    $actions[] = 'cancelled';
                }

                return $actions;
            });

            $results['checked'] += $candidateBatch->count();
            foreach ($actions as $action) {
                $results[$action]++;
            }
        });

        return $results;
    }

    private function recoverExport(ExportRequest $exportRequest): string
    {
        $metadata = $exportRequest->metadata ?? [];
        $attempts = ((int) ($metadata['automatic_recovery_attempts'] ?? 0)) + 1;
        $maxAttempts = max(1, (int) config('exports.stalled_max_recovery_attempts', 3));

        $metadata['automatic_recovery_attempts'] = $attempts;
        $metadata['automatic_recovery_last_at'] = now()->toIso8601String();

        if ($attempts > $maxAttempts) {
            $exportRequest->forceFill(['metadata' => $metadata]);
            $errorMessage = sprintf(
                'A exportação permaneceu parada após %d tentativas automáticas de recuperação.',
                $maxAttempts,
            );
            $exportRequest->markFailed($errorMessage);
            $this->logFailedJob($exportRequest, $errorMessage);

            return 'failed';
        }

        $exportRequest->forceFill(['metadata' => $metadata]);
        $exportRequest->cancel_requested_at = null;
        $exportRequest->markQueuedForRetry(
            "Fila recuperada automaticamente. Tentativa {$attempts} de {$maxAttempts}.",
        );

        ProcessExportRequestJob::dispatch($exportRequest->getKey())->afterCommit();

        return 'requeued';
    }

    private function isAutomaticallyCancelledExport(ExportRequest $exportRequest): bool
    {
        return $exportRequest->status === ExportRequest::STATUS_CANCELLED
            && $exportRequest->format !== 'processo'
            && in_array($exportRequest->status_message, $this->automaticCancellationMessages(), true);
    }

    /** @return array<int, string> */
    private function automaticCancellationMessages(): array
    {
        return [
            'Exportacao cancelada por inatividade na fila.',
            'Exportação cancelada por inatividade na fila.',
        ];
    }

    private function buildStatusMessage(ExportRequest $exportRequest): string
    {
        return $exportRequest->format === 'processo'
            ? 'Processo cancelado por inatividade na fila.'
            : 'Exportação cancelada por inatividade na fila.';
    }

    private function buildErrorMessage(ExportRequest $exportRequest): string
    {
        $statusLabel = $exportRequest->status === ExportRequest::STATUS_RUNNING ? 'em processamento' : 'na fila';

        return sprintf(
            'Solicitação cancelada automaticamente após ficar parada %s. ExportRequest=%s, tipo=%s, formato=%s.',
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

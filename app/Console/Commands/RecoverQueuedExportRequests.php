<?php

namespace App\Console\Commands;

use App\Jobs\ProcessExportRequestJob;
use App\Models\ExportRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecoverQueuedExportRequests extends Command
{
    protected $signature = 'exports:recover-queued {--limit=500 : Quantidade maxima de exportacoes a reenfileirar}';

    protected $description = 'Reencaminha para a fila persistente as exportacoes de arquivo que ficaram paradas';

    public function handle(): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));

        $ids = ExportRequest::query()
            ->where('status', ExportRequest::STATUS_QUEUED)
            ->where('format', '<>', 'processo')
            ->whereNull('session_ended_at')
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id');

        $dispatched = 0;

        foreach ($ids as $id) {
            $shouldDispatch = DB::transaction(function () use ($id): bool {
                $exportRequest = ExportRequest::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (! $exportRequest || $exportRequest->status !== ExportRequest::STATUS_QUEUED) {
                    return false;
                }

                if ($exportRequest->format === 'processo' || filled($exportRequest->session_ended_at)) {
                    return false;
                }

                $metadata = is_array($exportRequest->metadata) ? $exportRequest->metadata : [];
                $lastDispatch = $metadata['recovery_dispatch_at'] ?? null;

                if (filled($lastDispatch)) {
                    try {
                        if (Carbon::parse((string) $lastDispatch)->gt(now()->subMinutes(5))) {
                            return false;
                        }
                    } catch (\Throwable) {
                        // Valor legado invalido: permite nova tentativa e corrige o metadata.
                    }
                }

                $metadata['recovery_dispatch_at'] = now()->toIso8601String();
                $exportRequest->forceFill(['metadata' => $metadata])->save();

                return true;
            }, 3);

            if (! $shouldDispatch) {
                continue;
            }

            ProcessExportRequestJob::dispatch((string) $id);
            $dispatched++;
        }

        $this->info("Exportações reenfileiradas: {$dispatched}");

        return self::SUCCESS;
    }
}

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

    protected $description = 'Reencaminha para a fila Redis de exports as exportacoes de arquivo que ficaram paradas';

    public function handle(): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));

        // Remove apenas resíduos da tentativa temporária de usar MySQL como
        // transporte da fila de exportações. O ExportRequest continua intacto
        // e será reenviado ao Redis logo abaixo.
        $legacyDatabaseJobs = DB::table((string) config('queue.connections.database.table', 'jobs'))
            ->where('queue', 'exports')
            ->where('payload', 'like', '%ProcessExportRequestJob%')
            ->delete();

        $candidates = ExportRequest::query()
            ->where('status', ExportRequest::STATUS_QUEUED)
            ->where('format', '<>', 'processo')
            ->whereNull('session_ended_at');
        $dispatched = 0;

        $candidates->orderBy('id')->limit($limit)->chunkById(100, function ($candidateBatch) use (&$dispatched): void {
            $ids = $candidateBatch->pluck('id');
            $dispatchIds = DB::transaction(function () use ($ids): array {
                $requests = ExportRequest::query()
                    ->whereIn('id', $ids)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $dispatchIds = [];

                foreach ($ids as $id) {
                    $exportRequest = $requests->get($id);
                    if (! $exportRequest || $exportRequest->status !== ExportRequest::STATUS_QUEUED) {
                        continue;
                    }

                    if ($exportRequest->format === 'processo' || filled($exportRequest->session_ended_at)) {
                        continue;
                    }

                    $metadata = is_array($exportRequest->metadata) ? $exportRequest->metadata : [];
                    $lastDispatch = $metadata['recovery_dispatch_at'] ?? null;

                    if (filled($lastDispatch)) {
                        try {
                            if (Carbon::parse((string) $lastDispatch)->gt(now()->subMinutes(5))) {
                                continue;
                            }
                        } catch (\Throwable) {
                            // Valor legado invalido: permite nova tentativa e corrige o metadata.
                        }
                    }

                    $metadata['recovery_dispatch_at'] = now()->toIso8601String();
                    $exportRequest->forceFill(['metadata' => $metadata])->save();

                    $dispatchIds[] = (string) $id;
                }

                return $dispatchIds;
            }, 3);

            foreach ($dispatchIds as $id) {
                ProcessExportRequestJob::dispatch($id)->afterCommit();
                $dispatched++;
            }
        });

        $this->info("Jobs legados removidos do MySQL: {$legacyDatabaseJobs}");
        $this->info("Exportações reenfileiradas no Redis: {$dispatched}");

        return self::SUCCESS;
    }
}

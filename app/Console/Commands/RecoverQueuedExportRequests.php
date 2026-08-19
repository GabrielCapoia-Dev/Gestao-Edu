<?php

namespace App\Console\Commands;

use App\Jobs\ProcessExportRequestJob;
use App\Models\ExportRequest;
use Illuminate\Console\Command;

class RecoverQueuedExportRequests extends Command
{
    protected $signature = 'exports:recover-queued {--limit=500 : Quantidade maxima de exportacoes a reenfileirar}';

    protected $description = 'Reencaminha para a fila persistente as exportacoes de arquivo que ficaram paradas';

    public function handle(): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));

        $requests = ExportRequest::query()
            ->where('status', ExportRequest::STATUS_QUEUED)
            ->where('format', '<>', 'processo')
            ->whereNull('session_ended_at')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $dispatched = 0;

        foreach ($requests as $exportRequest) {
            ProcessExportRequestJob::dispatch((string) $exportRequest->getKey());
            $dispatched++;
        }

        $this->info("Exportações reenfileiradas: {$dispatched}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Exports\StalledExportRequestMonitorService;
use Illuminate\Console\Command;

class MonitorStalledExportRequests extends Command
{
    protected $signature = 'exports:monitor-stalled
        {--queued-minutes= : Minutos maximos permitidos para itens parados em fila}
        {--running-minutes= : Minutos maximos permitidos para itens sem progresso em processamento}';

    protected $description = 'Cancela exportacoes e processos travados na fila e registra a falha no banco da fila';

    public function handle(StalledExportRequestMonitorService $service): int
    {
        $result = $service->handle(
            queuedMinutes: $this->option('queued-minutes') !== null ? (int) $this->option('queued-minutes') : null,
            runningMinutes: $this->option('running-minutes') !== null ? (int) $this->option('running-minutes') : null,
        );

        $this->info("Solicitacoes verificadas: {$result['checked']}");
        $this->info("Solicitacoes canceladas: {$result['cancelled']}");

        return self::SUCCESS;
    }
}

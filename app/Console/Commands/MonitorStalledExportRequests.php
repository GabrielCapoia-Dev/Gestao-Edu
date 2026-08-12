<?php

namespace App\Console\Commands;

use App\Services\Exports\StalledExportRequestMonitorService;
use Illuminate\Console\Command;

class MonitorStalledExportRequests extends Command
{
    protected $signature = 'exports:monitor-stalled
        {--queued-minutes= : Minutos máximos permitidos para itens parados em fila}
        {--running-minutes= : Minutos máximos permitidos para itens sem progresso em processamento}';

    protected $description = 'Recupera exportações paradas e cancela processos que não podem ser reencaminhados com segurança';

    public function handle(StalledExportRequestMonitorService $service): int
    {
        $result = $service->handle(
            queuedMinutes: $this->option('queued-minutes') !== null ? (int) $this->option('queued-minutes') : null,
            runningMinutes: $this->option('running-minutes') !== null ? (int) $this->option('running-minutes') : null,
        );

        $this->info("Solicitações verificadas: {$result['checked']}");
        $this->info("Exportações reencaminhadas: {$result['requeued']}");
        $this->info("Exportações finalizadas com falha: {$result['failed']}");
        $this->info("Solicitações canceladas: {$result['cancelled']}");

        return self::SUCCESS;
    }
}

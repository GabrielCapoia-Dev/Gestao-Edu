<?php

namespace App\Console\Commands;

use App\Services\Dashboard\EventoCalendarioWorkflowService;
use Illuminate\Console\Command;

class RejeitarEventosCalendarioExpirados extends Command
{
    protected $signature = 'eventos-calendario:rejeitar-expirados';

    protected $description = 'Rejeita eventos pendentes cujo horário de início já passou.';

    public function handle(EventoCalendarioWorkflowService $workflow): int
    {
        $total = $workflow->rejeitarPendentesExpirados(now());

        $this->info("Eventos rejeitados automaticamente: {$total}");

        return self::SUCCESS;
    }
}

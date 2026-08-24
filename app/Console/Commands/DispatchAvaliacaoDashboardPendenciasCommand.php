<?php

namespace App\Console\Commands;

use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use Illuminate\Console\Command;

class DispatchAvaliacaoDashboardPendenciasCommand extends Command
{
    protected $signature = 'avaliacoes:dispatch-dashboard-pendencias
        {--limit=200 : Quantidade máxima de pendências a despachar}
        {--force : Ignora o intervalo de recuperação; destinado ao deploy}';

    protected $description = 'Recupera pendências incrementais do dashboard de avaliações sem executar rebuild completo.';

    public function handle(AvaliacaoDashboardFactsService $service): int
    {
        $incrementais = $service->dispatchPending(
            limit: max(1, min((int) $this->option('limit'), 1000)),
            force: (bool) $this->option('force'),
        );
        $escopos = $service->dispatchPendingScopes(
            limit: max(1, min((int) $this->option('limit'), 500)),
            force: (bool) $this->option('force'),
        );
        $reconciliadas = $service->reconcileFinishedIncrementalStatuses();

        $this->info(
            "Pendências de alunos encaminhadas: {$incrementais}; escopos estruturais encaminhados: {$escopos}; estados reconciliados: {$reconciliadas}."
        );

        return self::SUCCESS;
    }
}

<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildAvaliacaoDashboardFactsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [60, 180];

    public string $motivo = 'rebuild_explicito';

    /** Jobs serializados antes do fluxo manual não podem executar full após o deploy. */
    public bool $solicitacaoManualExplicita = false;

    public function __construct(
        public readonly int $avaliacaoId,
        string $motivo = 'rebuild_explicito',
    ) {
        $this->motivo = $motivo;
        $this->solicitacaoManualExplicita = true;
        $this->tries = (int) config('avaliacoes_dashboard.full.tries', 2);
        $this->timeout = (int) config('avaliacoes_dashboard.full.timeout', 600);
        $this->backoff = (array) config('avaliacoes_dashboard.full.backoff', [60, 180]);
        $this->onConnection($this->queueConnection());
        $this->onQueue((string) config('avaliacoes_dashboard.queue', 'dashboard'));
    }

    public function uniqueId(): string
    {
        return (string) $this->avaliacaoId;
    }

    public function uniqueFor(): int
    {
        return (int) config('avaliacoes_dashboard.full.unique_ttl', 3600);
    }

    /** Consome com segurança jobs antigos já serializados, sem recalcular fatos. */
    public function handle(): void {}

    private function queueConnection(): string
    {
        return (string) (config('queue.default') === 'sync'
            ? 'sync'
            : config('avaliacoes_dashboard.connection', 'dashboard_redis'));
    }
}

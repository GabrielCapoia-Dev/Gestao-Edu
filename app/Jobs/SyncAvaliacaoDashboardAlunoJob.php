<?php

namespace App\Jobs;

use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncAvaliacaoDashboardAlunoJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff;

    public function __construct(
        public readonly int $avaliacaoId,
        public readonly int $alunoId,
    ) {
        $this->tries = (int) config('avaliacoes_dashboard.incremental.tries', 3);
        $this->timeout = (int) config('avaliacoes_dashboard.incremental.timeout', 120);
        $this->backoff = (array) config('avaliacoes_dashboard.incremental.backoff', [10, 30, 60]);
        $this->onConnection($this->queueConnection());
        $this->onQueue((string) config('avaliacoes_dashboard.queue', 'dashboard'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('avaliacao-dashboard-aluno:'.$this->avaliacaoId.':'.$this->alunoId))
                ->releaseAfter(5)
                ->expireAfter((int) config('avaliacoes_dashboard.incremental.lock_ttl', 180)),
        ];
    }

    public function uniqueId(): string
    {
        return $this->avaliacaoId.':'.$this->alunoId;
    }

    public function uniqueFor(): int
    {
        return (int) config('avaliacoes_dashboard.incremental.unique_ttl', 3600);
    }

    public function handle(AvaliacaoDashboardFactsService $service): void
    {
        $service->processPendingDocumento($this->avaliacaoId, $this->alunoId, $this->attempts());
    }

    public function failed(?Throwable $exception): void
    {
        app(AvaliacaoDashboardFactsService::class)->markFailed(
            $this->avaliacaoId,
            $exception?->getMessage(),
            AvaliacaoDashboardFactsService::STATUS_INCREMENTAL_PROCESSING,
        );
    }

    private function queueConnection(): string
    {
        return (string) (config('queue.default') === 'sync'
            ? 'sync'
            : config('avaliacoes_dashboard.connection', 'dashboard_redis'));
    }
}

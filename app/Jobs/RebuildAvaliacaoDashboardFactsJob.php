<?php

namespace App\Jobs;

use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RebuildAvaliacaoDashboardFactsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $avaliacaoId)
    {
        $this->onQueue((string) config('exports.queue', 'exports'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('avaliacao-dashboard-facts:' . $this->avaliacaoId))
                ->expireAfter(900),
        ];
    }

    public function handle(AvaliacaoDashboardFactsService $service): void
    {
        $service->rebuild($this->avaliacaoId);
    }

    public function failed(?Throwable $exception): void
    {
        app(AvaliacaoDashboardFactsService::class)->markFailed($this->avaliacaoId, $exception?->getMessage());
    }
}

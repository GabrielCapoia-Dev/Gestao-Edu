<?php

namespace App\Jobs;

use App\Services\Avaliacoes\AvaliacaoDashboardTurmaResumoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AtualizarAvaliacaoDashboardTurmaResumoJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly int $avaliacaoId,
        public readonly int $turmaId,
    ) {
        $this->onConnection((string) config('queue.default'));
        $this->onQueue('default');
        $this->delay(now()->addMinutes(10));
    }

    public function uniqueId(): string
    {
        return $this->avaliacaoId.':'.$this->turmaId;
    }

    public function uniqueFor(): int
    {
        return 600;
    }

    public function handle(AvaliacaoDashboardTurmaResumoService $resumos): void
    {
        $resumos->recalcular($this->avaliacaoId, $this->turmaId);
    }
}

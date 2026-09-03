<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class LimitAvaliacaoExportConcurrency
{
    public function handle(object $job, Closure $next): void
    {
        $limit = max(0, (int) config('exports.avaliacao_max_concurrent', 2));

        if ($limit === 0) {
            $next($job);

            return;
        }

        $lock = null;
        $inicio = microtime(true);
        $limiteEspera = max(1, (int) config('exports.avaliacao_lock_wait', 840));

        while ($lock === null && microtime(true) - $inicio < $limiteEspera) {
            for ($slot = 1; $slot <= $limit; $slot++) {
                $candidate = Cache::lock('avaliacao-export-slot:'.$slot, (int) config('exports.lock_expiration', 1200));

                if ($candidate->get()) {
                    $lock = $candidate;

                    break;
                }
            }

            if ($lock === null) {
                usleep(250000);
            }
        }

        if ($lock === null) {
            throw new \RuntimeException('Não foi possível obter uma vaga para exportação de avaliação dentro do tempo limite.');
        }

        try {
            $next($job);
        } finally {
            $lock->release();
        }
    }
}

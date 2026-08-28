<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use App\Support\Avaliacoes\AvaliacaoPerformanceContext;

class PerformanceInstrumentation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('performance.instrumentation.enabled', false)) {
            return $next($request);
        }

        $start = microtime(true);
        $queryCount = 0;
        $queryTime = 0.0;
        $slowestQuery = null;

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTime, &$slowestQuery): void {
            $queryCount++;
            $queryTime += (float) $query->time;

            if ($slowestQuery === null || (float) $query->time > $slowestQuery['time_ms']) {
                $slowestQuery = [
                    'time_ms' => round((float) $query->time, 2),
                    'sql' => $this->sanitizeSql((string) $query->sql),
                ];
            }
        });

        /** @var Response $response */
        $response = $next($request);

        $elapsed = (microtime(true) - $start) * 1000;
        $routeName = (string) ($request->route()?->getName() ?? '');
        $threshold = $this->thresholdFor($request, $routeName);

        if (! $this->shouldLog($elapsed, $threshold)) {
            return $response;
        }

        $user = $request->user();

        Log::warning('Requisicao instrumentada', [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $routeName ?: null,
            'status' => $response->getStatusCode(),
            'elapsed_ms' => round($elapsed, 2),
            'query_count' => $queryCount,
            'query_time_ms' => round($queryTime, 2),
            'slowest_query' => $slowestQuery,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'response_bytes' => $this->responseBytes($response),
            'livewire_components' => $this->livewireComponents($request),
            'user_id' => $user?->getAuthIdentifier(),
            'user_roles' => $user && method_exists($user, 'getRoleNames')
                ? $user->getRoleNames()->values()->all()
                : [],
            'avaliacao' => app(AvaliacaoPerformanceContext::class)->all() ?: null,
        ]);

        return $response;
    }

    private function thresholdFor(Request $request, string $routeName): int
    {
        if (filled($routeName)) {
            $thresholds = config('performance.slow_threshold_ms', []);
            $threshold = (int) ($thresholds[$routeName] ?? data_get($thresholds, $routeName, 0));

            if ($threshold > 0) {
                return $threshold;
            }
        }

        if (
            (bool) config('performance.instrumentation.livewire_enabled', false)
            && Str::is(['livewire/update', 'livewire-*/*'], $request->path())
        ) {
            $thresholds = config('performance.slow_threshold_ms', []);

            return (int) ($thresholds['livewire.update'] ?? data_get($thresholds, 'livewire.update', 1500));
        }

        return (int) config('performance.instrumentation.slow_request_ms', 2000);
    }

    private function shouldLog(float $elapsed, int $threshold): bool
    {
        if ((bool) config('performance.instrumentation.log_all', false)) {
            return true;
        }

        return $threshold > 0 && $elapsed >= $threshold;
    }

    private function responseBytes(Response $response): ?int
    {
        $content = $response->getContent();

        return is_string($content) ? strlen($content) : null;
    }

    /**
     * @return array<int, string>
     */
    private function livewireComponents(Request $request): array
    {
        if (! Str::is(['livewire/update', 'livewire-*/*'], $request->path())) {
            return [];
        }

        $components = $request->input('components', []);

        if (! is_array($components)) {
            return [];
        }

        return collect($components)
            ->map(fn (mixed $component): ?string => data_get($component, 'snapshot.memo.name'))
            ->filter()
            ->values()
            ->all();
    }

    private function sanitizeSql(string $sql): string
    {
        $sql = preg_replace("/'[^']*'/", "'?'", $sql) ?? $sql;
        $sql = preg_replace('/"[^"]*"/', '"?"', $sql) ?? $sql;
        $sql = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $sql) ?? $sql;
        $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        return Str::limit(trim($sql), 500, '...');
    }
}

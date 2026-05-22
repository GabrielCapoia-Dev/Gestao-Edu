<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PerformanceInstrumentation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('performance.instrumentation.enabled', false)) {
            return $next($request);
        }

        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $elapsed = (microtime(true) - $start) * 1000;
        $routeName = (string) ($request->route()?->getName() ?? '');
        $threshold = $this->thresholdFor($request, $routeName);

        if ($threshold <= 0 || $elapsed < $threshold) {
            return $response;
        }

        Log::warning('Requisicao lenta instrumentada', [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $routeName ?: null,
            'elapsed_ms' => round($elapsed, 2),
            'response_bytes' => $this->responseBytes($response),
            'livewire_components' => $this->livewireComponents($request),
            'user_id' => $request->user()?->getAuthIdentifier(),
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

        return 0;
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
}

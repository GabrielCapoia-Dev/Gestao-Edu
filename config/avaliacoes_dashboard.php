<?php

$parseBackoff = static fn (string $value): array => array_values(array_filter(array_map(
    static fn (string $seconds): int => max(1, (int) trim($seconds)),
    explode(',', $value)
)));

return [
    'connection' => env('AVALIACOES_DASHBOARD_QUEUE_CONNECTION', 'dashboard_redis'),

    'queue' => env('AVALIACOES_DASHBOARD_QUEUE', 'dashboard'),

    'incremental' => [
        'timeout' => (int) env('AVALIACOES_DASHBOARD_INCREMENTAL_TIMEOUT', 120),
        'tries' => (int) env('AVALIACOES_DASHBOARD_INCREMENTAL_TRIES', 3),
        'max_attempts' => (int) env('AVALIACOES_DASHBOARD_INCREMENTAL_MAX_ATTEMPTS', 9),
        'recovery_after' => (int) env('AVALIACOES_DASHBOARD_INCREMENTAL_RECOVERY_AFTER', 180),
        'backoff' => $parseBackoff((string) env('AVALIACOES_DASHBOARD_INCREMENTAL_BACKOFF', '10,30,60')),
        'lock_ttl' => (int) env('AVALIACOES_DASHBOARD_INCREMENTAL_LOCK_TTL', 180),
        'unique_ttl' => (int) env('AVALIACOES_DASHBOARD_INCREMENTAL_UNIQUE_TTL', 3600),
    ],

    'scope' => [
        'max_attempts' => (int) env('AVALIACOES_DASHBOARD_SCOPE_MAX_ATTEMPTS', 9),
    ],

    'full' => [
        'timeout' => (int) env('AVALIACOES_DASHBOARD_FULL_TIMEOUT', 600),
        'tries' => (int) env('AVALIACOES_DASHBOARD_FULL_TRIES', 2),
        'backoff' => $parseBackoff((string) env('AVALIACOES_DASHBOARD_FULL_BACKOFF', '60,180')),
        'lock_ttl' => (int) env('AVALIACOES_DASHBOARD_FULL_LOCK_TTL', 720),
        'unique_ttl' => (int) env('AVALIACOES_DASHBOARD_FULL_UNIQUE_TTL', 3600),
    ],

    'worker' => [
        'timeout' => (int) env('AVALIACOES_DASHBOARD_QUEUE_WORKER_TIMEOUT', 600),
        'tries' => (int) env('AVALIACOES_DASHBOARD_QUEUE_TRIES', 3),
        'sleep' => (int) env('AVALIACOES_DASHBOARD_QUEUE_SLEEP', 3),
        'rest' => (int) env('AVALIACOES_DASHBOARD_QUEUE_REST', 1),
        'memory_mb' => (int) env('AVALIACOES_DASHBOARD_QUEUE_MEMORY_MB', 384),
        'max_jobs' => (int) env('AVALIACOES_DASHBOARD_QUEUE_MAX_JOBS', 250),
        'php_memory_limit' => env('AVALIACOES_DASHBOARD_PHP_MEMORY_LIMIT', '384M'),
        'nice' => (int) env('AVALIACOES_DASHBOARD_QUEUE_NICE', 10),
    ],
];

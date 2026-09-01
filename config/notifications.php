<?php

return [
    'queue' => env('NOTIFICATIONS_QUEUE', 'notifications'),

    'tries' => (int) env('NOTIFICATIONS_QUEUE_TRIES', 3),

    'timeout' => (int) env('NOTIFICATIONS_QUEUE_TIMEOUT', 60),

    'backoff' => array_values(array_filter(array_map(
        static fn (string $seconds): int => max(1, (int) trim($seconds)),
        explode(',', (string) env('NOTIFICATIONS_QUEUE_BACKOFF', '10,60,300'))
    ))),

    'unread_count_cache_ttl' => (int) env('NOTIFICATIONS_UNREAD_COUNT_CACHE_TTL', 120),

    'topbar_poll_interval_ms' => (int) env('NOTIFICATIONS_TOPBAR_POLL_INTERVAL_MS', 180000),

    'center_poll_interval_ms' => (int) env('NOTIFICATIONS_CENTER_POLL_INTERVAL_MS', 120000),
];

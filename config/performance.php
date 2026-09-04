<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Slow request thresholds (milliseconds)
    |--------------------------------------------------------------------------
    |
    | When a request exceeds these thresholds, a warning is logged.
    | Set to 0 or null to disable logging for that route.
    |
    */

    'slow_threshold_ms' => [
        'notifications.unreadCount' => (int) env('PERF_SLOW_UNREAD_COUNT_MS', 1000),
        'notifications.center' => (int) env('PERF_SLOW_NOTIFICATION_CENTER_MS', 2000),
        'presence.heartbeat' => (int) env('PERF_SLOW_HEARTBEAT_MS', 500),
        'livewire.update' => (int) env('PERF_SLOW_LIVEWIRE_MS', 1500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence heartbeat interval (seconds)
    |--------------------------------------------------------------------------
    |
    | Controls how often the JS heartbeat fires. Longer intervals reduce
    | server load but may show stale online status.
    |
    */

    'heartbeat_interval_seconds' => (int) env('PERF_HEARTBEAT_INTERVAL', 120),

    'presence_touch_min_interval_seconds' => (int) env('PERF_PRESENCE_TOUCH_MIN_INTERVAL', 90),

    'presence_online_window_seconds' => (int) env('PERF_PRESENCE_ONLINE_WINDOW', 180),

    'presence_history_sync_interval_seconds' => (int) env('PERF_PRESENCE_HISTORY_SYNC_INTERVAL', 900),

    'online_users_limit' => (int) env('PERF_ONLINE_USERS_LIMIT', 25),

    'offline_users_limit' => (int) env('PERF_OFFLINE_USERS_LIMIT', 25),

    /*
    |--------------------------------------------------------------------------
    | Livewire polling intervals (seconds)
    |--------------------------------------------------------------------------
    |
    | Controls how often Livewire components poll the server.
    |
    */

    'livewire_polling' => [
        'online_users' => (int) env('PERF_POLL_ONLINE_USERS', 60),
        'online_users_closed' => (int) env('PERF_POLL_ONLINE_USERS_CLOSED', 120),
        'exports_table' => (int) env('PERF_POLL_EXPORTS_TABLE', 30),
        'exports_auto_download' => (int) env('PERF_POLL_EXPORTS_AUTO_DOWNLOAD', 30),
        'exports_topbar_open' => (int) env('PERF_POLL_EXPORTS_TOPBAR_OPEN', 15),
        'exports_topbar_closed' => (int) env('PERF_POLL_EXPORTS_TOPBAR_CLOSED', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL values (seconds)
    |--------------------------------------------------------------------------
    */

    'cache_ttl' => [
        'online_users' => (int) env('PERF_CACHE_ONLINE_USERS', 60),
        'online_count' => (int) env('PERF_CACHE_ONLINE_COUNT', 60),
        'users_count' => (int) env('PERF_CACHE_USERS_COUNT', 900),
        'notification_form_options' => (int) env('PERF_CACHE_NOTIFICATION_FORM_OPTIONS', 1800),
        'active_exports' => (int) env('PERF_CACHE_ACTIVE_EXPORTS', 60),
        'inventory_dashboard' => (int) env('PERF_CACHE_INVENTORY_DASHBOARD', 300),
        'reports_dashboard' => (int) env('PERF_CACHE_REPORTS_DASHBOARD', 300),
        'avaliacoes_dashboard' => (int) env('PERF_CACHE_AVALIACOES_DASHBOARD', 120),
    ],

    'instrumentation' => [
        'enabled' => filter_var(env('PERF_INSTRUMENTATION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'sample_rate' => (float) env('PERF_INSTRUMENTATION_SAMPLE_RATE', 1),
        'livewire_enabled' => filter_var(env('PERF_LIVEWIRE_INSTRUMENTATION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'log_all' => filter_var(env('PERF_INSTRUMENTATION_LOG_ALL', false), FILTER_VALIDATE_BOOL),
        'slow_request_ms' => (int) env('PERF_SLOW_REQUEST_MS', 2000),
    ],
];

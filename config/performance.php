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

    'heartbeat_interval_seconds' => (int) env('PERF_HEARTBEAT_INTERVAL', 30),

    'presence_touch_min_interval_seconds' => (int) env('PERF_PRESENCE_TOUCH_MIN_INTERVAL', 20),

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
        'online_users' => (int) env('PERF_POLL_ONLINE_USERS', 30),
        'exports_table' => (int) env('PERF_POLL_EXPORTS_TABLE', 15),
        'exports_auto_download' => (int) env('PERF_POLL_EXPORTS_AUTO_DOWNLOAD', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL values (seconds)
    |--------------------------------------------------------------------------
    */

    'cache_ttl' => [
        'online_users' => (int) env('PERF_CACHE_ONLINE_USERS', 15),
        'online_count' => (int) env('PERF_CACHE_ONLINE_COUNT', 10),
        'users_count' => (int) env('PERF_CACHE_USERS_COUNT', 60),
        'notification_form_options' => (int) env('PERF_CACHE_NOTIFICATION_FORM_OPTIONS', 300),
        'active_exports' => (int) env('PERF_CACHE_ACTIVE_EXPORTS', 10),
        'inventory_dashboard' => (int) env('PERF_CACHE_INVENTORY_DASHBOARD', 60),
        'reports_dashboard' => (int) env('PERF_CACHE_REPORTS_DASHBOARD', 60),
    ],

    'instrumentation' => [
        'enabled' => filter_var(env('PERF_INSTRUMENTATION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'livewire_enabled' => filter_var(env('PERF_LIVEWIRE_INSTRUMENTATION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'log_all' => filter_var(env('PERF_INSTRUMENTATION_LOG_ALL', false), FILTER_VALIDATE_BOOL),
        'slow_request_ms' => (int) env('PERF_SLOW_REQUEST_MS', 2000),
    ],
];

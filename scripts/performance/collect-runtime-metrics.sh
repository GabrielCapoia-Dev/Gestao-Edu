#!/usr/bin/env bash
set -euo pipefail

output="${1:-/tmp/gestao-edu-runtime-metrics.csv}"
duration="${2:-900}"
interval="${3:-1}"
status_output="${output%.csv}-fpm-status.jsonl"
process_output="${output%.csv}-processes.csv"
host_output="${output%.csv}-host.csv"
mysql_output="${output%.csv}-mysql.jsonl"
queue_output="${output%.csv}-queues.csv"

printf '%s\n' 'timestamp,container,cpu_percent,memory_usage' > "$output"
: > "$status_output"
printf '%s\n' 'timestamp,pid,process,cpu_percent,rss_kb' > "$process_output"
printf '%s\n' 'timestamp,mem_available_kb,swap_free_kb,app_restarts,db_restarts,redis_restarts' > "$host_output"
: > "$mysql_output"
printf '%s\n' 'timestamp,dashboard,default,notifications,exports,imports' > "$queue_output"

end=$((SECONDS + duration))
next_status=0
next_diagnostics=0

while (( SECONDS < end )); do
    timestamp="$(date '+%Y-%m-%dT%H:%M:%S.%N%:z')"

    docker stats --no-stream \
        --format '{{.Name}},{{.CPUPerc}},{{.MemUsage}}' \
        laravel-app-gestao-edu \
        laravel-db-gestao-edu \
        laravel-redis-gestao-edu \
        laravel-queue-gestao-edu \
        laravel-queue-default-gestao-edu \
        laravel-queue-imports-gestao-edu \
        laravel-queue-notifications-gestao-edu \
        laravel-pulse-gestao-edu 2>/dev/null \
        | sed "s/^/${timestamp},/" >> "$output" || true

    if (( SECONDS >= next_status )); then
        status="$(docker exec laravel-app-gestao-edu sh -lc \
            "curl -sk --max-time 2 -H 'Host: edu.hubdetestes.online' 'https://127.0.0.1/fpm-status?json'" 2>/dev/null || true)"
        if [[ -n "$status" ]]; then
            printf '{"timestamp":"%s","status":%s}\n' "$timestamp" "$status" >> "$status_output"
        fi
        next_status=$((SECONDS + 5))
    fi

    docker exec laravel-app-gestao-edu ps --no-headers -eo pid,comm,pcpu,rss 2>/dev/null \
        | awk -v timestamp="$timestamp" '$2 ~ /^(php-fpm|php|nginx)$/ { print timestamp "," $1 "," $2 "," $3 "," $4 }' \
        >> "$process_output" || true

    mem_available="$(awk '/^MemAvailable:/ { print $2 }' /proc/meminfo)"
    swap_free="$(awk '/^SwapFree:/ { print $2 }' /proc/meminfo)"
    app_restarts="$(docker inspect -f '{{.RestartCount}}' laravel-app-gestao-edu 2>/dev/null || echo 0)"
    db_restarts="$(docker inspect -f '{{.RestartCount}}' laravel-db-gestao-edu 2>/dev/null || echo 0)"
    redis_restarts="$(docker inspect -f '{{.RestartCount}}' laravel-redis-gestao-edu 2>/dev/null || echo 0)"
    printf '%s,%s,%s,%s,%s,%s\n' \
        "$timestamp" "$mem_available" "$swap_free" "$app_restarts" "$db_restarts" "$redis_restarts" \
        >> "$host_output"

    if (( SECONDS >= next_diagnostics )); then
        mysql_status="$(docker exec laravel-db-gestao-edu sh -lc \
            'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -NBe "SELECT JSON_OBJECTAGG(VARIABLE_NAME, CAST(VARIABLE_VALUE AS UNSIGNED)) FROM performance_schema.global_status WHERE VARIABLE_NAME IN (\"Threads_connected\",\"Threads_running\",\"Innodb_row_lock_current_waits\",\"Innodb_row_lock_time\",\"Innodb_row_lock_waits\",\"Innodb_buffer_pool_read_requests\",\"Innodb_buffer_pool_reads\",\"Created_tmp_disk_tables\",\"Questions\")"' \
            2>/dev/null || true)"
        if [[ -n "$mysql_status" ]]; then
            printf '{"timestamp":"%s","status":%s}\n' "$timestamp" "$mysql_status" >> "$mysql_output"
        fi

        dashboard="$(docker exec laravel-redis-gestao-edu redis-cli LLEN gestao_edu_database_queues:dashboard 2>/dev/null || echo 0)"
        default="$(docker exec laravel-redis-gestao-edu redis-cli LLEN gestao_edu_database_queues:default 2>/dev/null || echo 0)"
        notifications="$(docker exec laravel-redis-gestao-edu redis-cli LLEN gestao_edu_database_queues:notifications 2>/dev/null || echo 0)"
        exports="$(docker exec laravel-redis-gestao-edu redis-cli LLEN gestao_edu_database_queues:exports 2>/dev/null || echo 0)"
        imports="$(docker exec laravel-redis-gestao-edu redis-cli LLEN gestao_edu_database_queues:imports 2>/dev/null || echo 0)"
        printf '%s,%s,%s,%s,%s,%s\n' \
            "$timestamp" "$dashboard" "$default" "$notifications" "$exports" "$imports" \
            >> "$queue_output"
        next_diagnostics=$((SECONDS + 5))
    fi

    sleep "$interval"
done

printf '%s\n' "$output"
printf '%s\n' "$status_output"
printf '%s\n' "$process_output"
printf '%s\n' "$host_output"
printf '%s\n' "$mysql_output"
printf '%s\n' "$queue_output"

#!/usr/bin/env bash
set -euo pipefail

output="${1:-/tmp/gestao-edu-runtime-metrics.csv}"
duration="${2:-900}"
interval="${3:-1}"
status_output="${output%.csv}-fpm-status.jsonl"

printf '%s\n' 'timestamp,container,cpu_percent,memory_usage' > "$output"
: > "$status_output"

end=$((SECONDS + duration))
next_status=0

while (( SECONDS < end )); do
    timestamp="$(date --iso-8601=ns)"

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

    sleep "$interval"
done

printf '%s\n' "$output"
printf '%s\n' "$status_output"

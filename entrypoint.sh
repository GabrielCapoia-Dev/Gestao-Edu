#!/bin/bash
set -e

cd /var/www

READY_FILE="/var/www/bootstrap/cache/runtime-ready"

wait_for_runtime_ready() {
    local timeout="${QUEUE_BOOT_WAIT_SECONDS:-960}"
    local waited=0

    while [ ! -s "$READY_FILE" ]; do
        if [ "$waited" -ge "$timeout" ]; then
            echo "[queue] Runtime da aplicacao nao ficou pronto em ${timeout}s." >&2
            exit 1
        fi

        sleep 2
        waited=$((waited + 2))
    done
}

run_queue_worker() {
    local role="${1:-default}"
    local queue_name="default"
    local sleep_seconds="3"
    local rest_seconds="0"
    local timeout_seconds="300"
    local tries="3"
    local memory_mb="384"
    local max_time="${QUEUE_WORKER_MAX_TIME:-3600}"
    local max_jobs="${QUEUE_WORKER_MAX_JOBS:-500}"
    local php_memory_limit="512M"
    local nice_level=""

    wait_for_runtime_ready

    mkdir -p \
        storage/app/private/exports \
        storage/app/private/imports \
        storage/logs \
        bootstrap/cache

    chown -R www-data:www-data \
        storage/app/private/exports \
        storage/app/private/imports \
        storage/logs \
        bootstrap/cache

    case "$role" in
        exports)
            queue_name="exports"
            sleep_seconds="${EXPORTS_QUEUE_SLEEP:-5}"
            rest_seconds="${EXPORTS_QUEUE_REST:-1}"
            timeout_seconds="${EXPORTS_JOB_TIMEOUT:-900}"
            tries="${EXPORTS_QUEUE_TRIES:-3}"
            memory_mb="${EXPORTS_QUEUE_MEMORY_MB:-384}"
            max_jobs="${EXPORTS_QUEUE_MAX_JOBS:-250}"
            php_memory_limit="${EXPORTS_PHP_MEMORY_LIMIT:-512M}"
            nice_level="${EXPORTS_QUEUE_NICE:-10}"
            ;;
        imports)
            queue_name="imports"
            sleep_seconds="${IMPORTS_QUEUE_SLEEP:-5}"
            rest_seconds="${IMPORTS_QUEUE_REST:-1}"
            timeout_seconds="${IMPORTS_JOB_TIMEOUT:-1800}"
            tries="${IMPORTS_QUEUE_TRIES:-2}"
            memory_mb="${IMPORTS_QUEUE_MEMORY_MB:-384}"
            max_jobs="${IMPORTS_QUEUE_MAX_JOBS:-100}"
            php_memory_limit="${IMPORTS_PHP_MEMORY_LIMIT:-512M}"
            nice_level="${IMPORTS_QUEUE_NICE:-10}"
            ;;
        notifications)
            queue_name="notifications"
            sleep_seconds="${NOTIFICATIONS_QUEUE_SLEEP:-3}"
            rest_seconds="${NOTIFICATIONS_QUEUE_REST:-0}"
            timeout_seconds="${NOTIFICATIONS_QUEUE_TIMEOUT:-60}"
            tries="${NOTIFICATIONS_QUEUE_TRIES:-3}"
            memory_mb="${NOTIFICATIONS_QUEUE_MEMORY_MB:-384}"
            max_jobs="${NOTIFICATIONS_QUEUE_MAX_JOBS:-500}"
            php_memory_limit="256M"
            ;;
        default)
            queue_name="default"
            sleep_seconds="${DEFAULT_QUEUE_SLEEP:-3}"
            rest_seconds="${DEFAULT_QUEUE_REST:-0}"
            timeout_seconds="${DEFAULT_QUEUE_TIMEOUT:-300}"
            tries="${DEFAULT_QUEUE_TRIES:-3}"
            memory_mb="${DEFAULT_QUEUE_MEMORY_MB:-384}"
            max_jobs="${DEFAULT_QUEUE_MAX_JOBS:-500}"
            php_memory_limit="256M"
            ;;
        *)
            echo "[queue] Papel de worker invalido: ${role}" >&2
            exit 1
            ;;
    esac

    local worker_command="php -d memory_limit=${php_memory_limit} artisan queue:work redis --queue=${queue_name} --sleep=${sleep_seconds} --rest=${rest_seconds} --timeout=${timeout_seconds} --tries=${tries} --memory=${memory_mb} --max-time=${max_time} --max-jobs=${max_jobs}"

    if [ -n "$nice_level" ]; then
        worker_command="nice -n ${nice_level} ${worker_command}"
    fi

    echo "[queue] Iniciando worker role=${role} queue=${queue_name} timeout=${timeout_seconds}s memory=${memory_mb}MB"

    exec su -s /bin/sh www-data -c "$worker_command"
}

if [ "${1:-}" = "queue-worker" ]; then
    run_queue_worker "${2:-default}"
fi

rm -f "$READY_FILE"

# ── Permissões (aplicadas após o volume ser montado) ───────────────────────
chown -R www-data:www-data \
    /var/www/storage \
    /var/www/bootstrap/cache
chmod -R 775 \
    /var/www/storage \
    /var/www/bootstrap/cache

# ── Dependências PHP ────────────────────────────────────────────────────────
# Run on every boot so dependency changes from a deploy are applied even when
# vendor/ is persisted through the bind mount.
if [ "$APP_ENV" = "production" ]; then
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
else
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# ── APP_KEY ────────────────────────────────────────────────────────────────
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    php artisan key:generate --force
fi

# ── Migrations ─────────────────────────────────────────────────────────────
php artisan migrate --force --seed

# ── Permissoes ─────────────────────────────────────────────────────────────
php artisan permissoes:criar

# ── Storage link ───────────────────────────────────────────────────────────
php artisan storage:link --force 2>/dev/null || true

# ── Limpa caches antigos ───────────────────────────────────────────────────
php artisan config:clear
php artisan permission:cache-reset
php artisan route:clear
php artisan event:clear
php artisan view:clear

# ── Reconstrói cache com variáveis de ambiente corretas do runtime ──────────
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan filament:cache-components
php artisan livewire:publish --assets
php artisan filament:assets

date -u +"%Y-%m-%dT%H:%M:%SZ" > "$READY_FILE"
chown www-data:www-data "$READY_FILE"
chmod 664 "$READY_FILE"

if command -v gzip >/dev/null 2>&1; then
    for asset_dir in public/css public/js public/vendor/livewire; do
        [ -d "$asset_dir" ] || continue

        find "$asset_dir" -type f \( \
            -name '*.css' -o \
            -name '*.js' -o \
            -name '*.json' -o \
            -name '*.map' -o \
            -name '*.svg' \
        \) -exec gzip -kf {} \;
    done
fi

# ── Inicia php-fpm em background e nginx em foreground ─────────────────────
php-fpm -D
nginx -g "daemon off;"

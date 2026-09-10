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
    local queue_connection="redis"
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
            queue_connection="exports_redis"
            sleep_seconds="${EXPORTS_QUEUE_SLEEP:-3}"
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
            queue_connection="redis"
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
            queue_connection="redis"
            sleep_seconds="${NOTIFICATIONS_QUEUE_SLEEP:-3}"
            rest_seconds="${NOTIFICATIONS_QUEUE_REST:-0}"
            timeout_seconds="${NOTIFICATIONS_QUEUE_TIMEOUT:-60}"
            tries="${NOTIFICATIONS_QUEUE_TRIES:-3}"
            memory_mb="${NOTIFICATIONS_QUEUE_MEMORY_MB:-384}"
            max_jobs="${NOTIFICATIONS_QUEUE_MAX_JOBS:-500}"
            php_memory_limit="256M"
            nice_level="${NOTIFICATIONS_QUEUE_NICE:-10}"

            # O bootstrap atual do Laravel ultrapassa 128 MB no Hub. Abaixo
            # deste piso o worker encerra imediatamente com código 12.
            if [ "$memory_mb" -lt 256 ]; then
                memory_mb=256
            fi
            ;;
        default)
            queue_name="default"
            queue_connection="redis"
            sleep_seconds="${DEFAULT_QUEUE_SLEEP:-3}"
            rest_seconds="${DEFAULT_QUEUE_REST:-0}"
            timeout_seconds="${DEFAULT_QUEUE_TIMEOUT:-300}"
            tries="${DEFAULT_QUEUE_TRIES:-3}"
            memory_mb="${DEFAULT_QUEUE_MEMORY_MB:-384}"
            max_jobs="${DEFAULT_QUEUE_MAX_JOBS:-500}"
            php_memory_limit="256M"
            nice_level="${DEFAULT_QUEUE_NICE:-10}"
            ;;
        *)
            echo "[queue] Papel de worker invalido: ${role}" >&2
            exit 1
            ;;
    esac

    local worker_command="php -d memory_limit=${php_memory_limit} artisan queue:work ${queue_connection} --queue=${queue_name} --sleep=${sleep_seconds} --rest=${rest_seconds} --timeout=${timeout_seconds} --tries=${tries} --memory=${memory_mb} --max-time=${max_time} --max-jobs=${max_jobs}"

    if [ -n "$nice_level" ]; then
        worker_command="nice -n ${nice_level} ${worker_command}"
    fi

    echo "[queue] Iniciando worker role=${role} connection=${queue_connection} queue=${queue_name} timeout=${timeout_seconds}s memory=${memory_mb}MB"

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
php artisan filament:optimize
php artisan livewire:publish --assets
php artisan filament:assets

# Reenvia para o Redis exportacoes de arquivo que possam ter ficado apenas no
# registro persistente durante um deploy/restart anterior. O comando possui
# controle de intervalo para nao gerar reenfileiramento excessivo.
php artisan exports:recover-queued --limit=1000 || true

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

# PHP-FPM must follow the CPU capacity actually assigned to the container.
# Evaluation Livewire requests are CPU-bound; a large pool on a one-CPU host
# only creates contention and makes every request slower. Keep an explicit
# environment override for hosts with reserved capacity.
configure_php_fpm_pool() {
    local detected_cpus="$(nproc 2>/dev/null || echo 1)"
    local process_manager="${PHP_FPM_PM:-static}"
    local max_children="${PHP_FPM_MAX_CHILDREN:-2}"
    local max_requests="${PHP_FPM_MAX_REQUESTS:-300}"
    local start_servers="${PHP_FPM_START_SERVERS:-1}"
    local min_spare_servers="${PHP_FPM_MIN_SPARE_SERVERS:-1}"
    local max_spare_servers="${PHP_FPM_MAX_SPARE_SERVERS:-2}"

    [[ "$detected_cpus" =~ ^[0-9]+$ ]] || detected_cpus=1
    if [ -z "${PHP_FPM_MAX_CHILDREN:-}" ]; then
        max_children=$((detected_cpus * 2))
    fi
    if [ -z "${PHP_FPM_START_SERVERS:-}" ]; then
        start_servers="$detected_cpus"
    fi
    if [ -z "${PHP_FPM_MIN_SPARE_SERVERS:-}" ]; then
        min_spare_servers="$detected_cpus"
    fi
    if [ -z "${PHP_FPM_MAX_SPARE_SERVERS:-}" ]; then
        max_spare_servers="$max_children"
    fi

    [[ "$max_children" =~ ^[0-9]+$ ]] || max_children=2
    [[ "$max_requests" =~ ^[0-9]+$ ]] || max_requests=300
    [[ "$start_servers" =~ ^[0-9]+$ ]] || start_servers=1
    [[ "$min_spare_servers" =~ ^[0-9]+$ ]] || min_spare_servers=1
    [[ "$max_spare_servers" =~ ^[0-9]+$ ]] || max_spare_servers="$max_children"

    (( max_children < 2 )) && max_children=2
    (( start_servers < 1 )) && start_servers=1
    (( min_spare_servers < 1 )) && min_spare_servers=1
    (( start_servers > max_children )) && start_servers="$max_children"
    (( min_spare_servers > max_children )) && min_spare_servers="$max_children"
    (( max_spare_servers < min_spare_servers )) && max_spare_servers="$min_spare_servers"
    (( max_spare_servers > max_children )) && max_spare_servers="$max_children"

    if [ "$process_manager" != "static" ] && [ "$process_manager" != "dynamic" ]; then
        process_manager="static"
    fi

    cat >> /usr/local/etc/php-fpm.d/www.conf <<EOF

; Runtime pool sizing based on container CPU capacity.
pm = ${process_manager}
pm.max_children = ${max_children}
pm.max_requests = ${max_requests}
EOF

    if [ "$process_manager" = "dynamic" ]; then
        cat >> /usr/local/etc/php-fpm.d/www.conf <<EOF
pm.start_servers = ${start_servers}
pm.min_spare_servers = ${min_spare_servers}
pm.max_spare_servers = ${max_spare_servers}
EOF
    fi

    echo "[php-fpm] Pool runtime: cpus=${detected_cpus} pm=${process_manager} max_children=${max_children} max_requests=${max_requests}"
}

start_web_runtime() {
    local runtime="${APP_RUNTIME:-fpm}"
    local nginx_templates="/etc/nginx/runtime-templates"

    if [ -d /etc/nginx/templates ]; then
        nginx_templates="/etc/nginx/templates"
    fi

    render_nginx_template() {
        local template="$1"
        local certificate="/etc/nginx/certs/fullchain.pem"
        local certificate_key="/etc/nginx/certs/privkey.pem"

        [ -f /etc/nginx/certs/fullchain1.pem ] && certificate="/etc/nginx/certs/fullchain1.pem"
        [ -f /etc/nginx/certs/privkey1.pem ] && certificate_key="/etc/nginx/certs/privkey1.pem"

        if [ -f /etc/nginx/certs/live/*/fullchain.pem ]; then
            certificate=$(printf '%s\n' /etc/nginx/certs/live/*/fullchain.pem | head -n 1)
        fi
        if [ -f /etc/nginx/certs/live/*/privkey.pem ]; then
            certificate_key=$(printf '%s\n' /etc/nginx/certs/live/*/privkey.pem | head -n 1)
        fi

        sed \
            -e "s|__NGINX_SERVER_NAME__|${NGINX_SERVER_NAME:-gestaoedu.umuarama.pr.gov.br}|g" \
            -e "s|__NGINX_SSL_CERTIFICATE__|${certificate}|g" \
            -e "s|__NGINX_SSL_CERTIFICATE_KEY__|${certificate_key}|g" \
            "$template" > /etc/nginx/conf.d/default.conf
    }

    case "$runtime" in
        octane)
            render_nginx_template "${nginx_templates}/octane.conf"
            nginx -t
            nginx

            echo "[octane] Runtime: server=${OCTANE_SERVER:-swoole} workers=${OCTANE_WORKERS:-1} max_requests=${OCTANE_MAX_REQUESTS:-500}"

            exec su -s /bin/sh www-data -c \
                "php artisan octane:start --server=${OCTANE_SERVER:-swoole} --host=127.0.0.1 --port=8000 --workers=${OCTANE_WORKERS:-1} --max-requests=${OCTANE_MAX_REQUESTS:-500}"
            ;;
        fpm)
            render_nginx_template "${nginx_templates}/default.conf"
            configure_php_fpm_pool
            php-fpm -D
            exec nginx -g "daemon off;"
            ;;
        *)
            echo "[runtime] APP_RUNTIME invalido: ${runtime}. Use fpm ou octane." >&2
            exit 1
            ;;
    esac
}

start_web_runtime

# ── Inicia php-fpm em background e nginx em foreground ─────────────────────

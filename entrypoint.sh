#!/bin/bash
set -e

cd /var/www

READY_FILE="/var/www/bootstrap/cache/runtime-ready"
rm -f "$READY_FILE"

# ── Permissões (aplicadas após o volume ser montado) ───────────────────────
chown -R www-data:www-data \
    /var/www/storage \
    /var/www/bootstrap/cache
chmod -R 775 \
    /var/www/storage \
    /var/www/bootstrap/cache

# ── Dependências PHP ───────────────────────────────────────────────────────
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

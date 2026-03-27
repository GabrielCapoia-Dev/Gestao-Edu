#!/bin/bash
set -e

cd /var/www

if [ ! -f "vendor/autoload.php" ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    php artisan key:generate --force
fi

php artisan migrate --force --seed

php artisan storage:link --force 2>/dev/null || true

# ── Build dos assets ───────────────────────────────────────────────────────
if [ ! -d "public/build" ]; then
    npm ci
    npm run build
fi

# ── Limpa caches antigos antes de reconstruir ──────────────────────────────
php artisan config:clear
php artisan route:clear
php artisan event:clear
php artisan view:clear

# ── Reconstrói cache com variáveis de ambiente corretas do runtime ──────────
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan filament:cache-components

# ── Inicia php-fpm em background e nginx em foreground ─────────────────────
php-fpm -D
nginx -g "daemon off;"
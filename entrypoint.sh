#!/bin/bash
set -e

cd /var/www

if [ ! -f "vendor/autoload.php" ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    php artisan key:generate --force
fi


# ── Assets ─────────────────────────────────────────────────────────────────
if [ ! -d "node_modules" ]; then
    npm install
fi

npm run dev


php artisan migrate --force --seed

php artisan storage:link --force 2>/dev/null || true

# ── Cache do Laravel (impacto enorme no tempo de boot de cada request) ─────
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan filament:cache-components




php artisan serve --host=0.0.0.0 --port=${APP_PORT}
#!/bin/bash
set -e

cd /var/www

if [ ! -f "vendor/autoload.php" ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    php artisan key:generate --force
fi

php artisan migrate --force

php artisan storage:link --force 2>/dev/null || true

php artisan serve --host=0.0.0.0 --port=${APP_PORT}
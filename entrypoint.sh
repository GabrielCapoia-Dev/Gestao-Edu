#!/bin/bash
set -e

cd /var/www

# ── Permissões (aplicadas após o volume ser montado) ───────────────────────
chown -R www-data:www-data \
    /var/www/storage \
    /var/www/bootstrap/cache
chmod -R 775 \
    /var/www/storage \
    /var/www/bootstrap/cache

# ── Dependências PHP ───────────────────────────────────────────────────────
if [ ! -f "vendor/autoload.php" ]; then
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
php artisan filament:cache-components
php artisan filament:assets


# ── Inicia php-fpm em background e nginx em foreground ─────────────────────
php-fpm -D
nginx -g "daemon off;"

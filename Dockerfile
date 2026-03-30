FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    nano \
    vim \
    nginx \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    && docker-php-ext-install \
    intl pdo pdo_mysql zip mbstring exif pcntl bcmath gd opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ── Node.js 22 ─────────────────────────────────────────────────────────────
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ── OPcache ────────────────────────────────────────────────────────────────
RUN echo "opcache.enable=1"                    >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256"      >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16"  >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=1"       >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=0"           >> /usr/local/etc/php/conf.d/opcache.ini

# ── PHP uploads / memory ───────────────────────────────────────────────────
RUN echo "upload_max_filesize=10M"  >  /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=20M"     >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=256M"     >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time=120" >> /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# ── Copia o código da aplicação ────────────────────────────────────────────
COPY --chown=www-data:www-data . .

# ── Dependências PHP (sem dev) ─────────────────────────────────────────────
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# ── Build dos assets e remove node_modules ─────────────────────────────────
RUN npm ci && npm run build && rm -rf node_modules

# ── Nginx ──────────────────────────────────────────────────────────────────
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# ── Entrypoint ─────────────────────────────────────────────────────────────
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ── Permissões de storage e cache ──────────────────────────────────────────
RUN mkdir -p \
    /var/www/storage/app/public \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
    && chown -R www-data:www-data \
    /var/www/storage \
    /var/www/bootstrap/cache \
    && chmod -R 775 \
    /var/www/storage \
    /var/www/bootstrap/cache \
    && chmod 1777 /tmp

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
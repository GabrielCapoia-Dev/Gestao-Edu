FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    $PHPIZE_DEPS \
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
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ── Node.js 22 ─────────────────────────────────────────────────────────────
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# OPcache tuned for production. Deploys should restart the container so the
# opcode cache is rebuilt with the new code.
RUN echo "opcache.enable=1"                    >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256"      >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16"  >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0"       >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=60"          >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.save_comments=1"             >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit=0"                       >> /usr/local/etc/php/conf.d/opcache.ini

# Safe fallback for the PHP-FPM pool. The entrypoint recalculates these values
# from the CPUs visible at runtime; PHP_FPM_MAX_CHILDREN can override it per
# environment when the host has a deliberately reserved capacity.
RUN { \
        echo ""; \
        echo "; Gestao Edu production pool overrides"; \
        echo "pm = dynamic"; \
        echo "pm.max_children = 2"; \
        echo "pm.start_servers = 1"; \
        echo "pm.min_spare_servers = 1"; \
        echo "pm.max_spare_servers = 2"; \
        echo "pm.max_requests = 300"; \
        echo "request_terminate_timeout = 60s"; \
        echo "pm.status_path = /fpm-status"; \
        echo "ping.path = /fpm-ping"; \
        echo "slowlog = /var/log/php-fpm/slow.log"; \
        echo "request_slowlog_timeout = 5s"; \
        echo "catch_workers_output = yes"; \
    } >> /usr/local/etc/php-fpm.d/www.conf \
    && mkdir -p /var/log/php-fpm \
    && touch /var/log/php-fpm/slow.log \
    && chown www-data:www-data /var/log/php-fpm/slow.log

# ── PHP uploads / memory ───────────────────────────────────────────────────
RUN echo "upload_max_filesize=10M"   >  /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=20M"      >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=256M"      >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time=120" >> /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# ── Nginx ──────────────────────────────────────────────────────────────────
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# ── Entrypoint ─────────────────────────────────────────────────────────────
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ── Diretórios base (permissões aplicadas no entrypoint após mount) ─────────
RUN mkdir -p \
    /var/www/storage/app/public \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
    && chmod 1777 /tmp

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]

FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    libpq-dev libjpeg-dev libfreetype6-dev libicu-dev \
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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
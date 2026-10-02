ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli

# Native libs: libvips for processing, the rest for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libpq-dev libzip-dev libvips-tools \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql zip opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

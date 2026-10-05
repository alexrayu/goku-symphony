ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli

# Native libs: libvips for processing, the rest for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libpq-dev libzip-dev libvips-tools \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql zip opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Chapter archives arrive in one request; prod Nginx needs a matching client_max_body_size.
RUN printf 'upload_max_filesize=200M\npost_max_size=200M\n' > "$PHP_INI_DIR/conf.d/uploads.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

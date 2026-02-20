# Producción: Laravel + PHP 8.4 (composer.lock requiere paquetes PHP 8.4+)
FROM php:8.4-cli-alpine AS base

RUN apk add --no-cache \
    libpq-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        pcntl \
        bcmath \
        opcache \
    && apk del $PHPIZE_DEPS

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Dependencias (sin dev para producción)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# Build front: desde base para tener PHP (wayfinder ejecuta `php artisan wayfinder:generate` en el build)
FROM base AS frontend
RUN apk add --no-cache nodejs npm
RUN npm ci
RUN npm run build

# Imagen final
FROM base AS app
COPY --from=frontend /var/www/html/public/build /var/www/html/public/build

# Script que ejecuta migraciones, seeder y arranca el servidor
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["/entrypoint.sh"]

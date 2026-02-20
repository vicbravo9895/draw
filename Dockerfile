# Producción: Laravel + PHP 8.2
FROM php:8.2-cli-alpine AS base

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

# Build front (necesita node en etapa de build)
FROM node:20-alpine AS frontend
WORKDIR /var/www/html
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
COPY --from=base /var/www/html/vendor /var/www/html/vendor
COPY --from=base /var/www/html/app /var/www/html/app
COPY --from=base /var/www/html/bootstrap /var/www/html/bootstrap
COPY --from=base /var/www/html/config /var/www/html/config
COPY --from=base /var/www/html/database /var/www/html/database
COPY --from=base /var/www/html/public /var/www/html/public
COPY --from=base /var/www/html/resources /var/www/html/resources
COPY --from=base /var/www/html/routes /var/www/html/routes
RUN npm run build

# Imagen final
FROM base AS app
COPY --from=frontend /var/www/html/public/build /var/www/html/public/build

# Script que ejecuta migraciones, seeder y arranca el servidor
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["/entrypoint.sh"]

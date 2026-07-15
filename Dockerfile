FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize

FROM php:8.3-fpm-alpine AS backend

WORKDIR /var/www/html

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        zip

COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app ./
COPY docker/entrypoint.sh /usr/local/bin/recycling-backend-entrypoint

RUN chmod +x /usr/local/bin/recycling-backend-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf "[www]\nclear_env = no\ncatch_workers_output = yes\n" > /usr/local/etc/php-fpm.d/zz-docker-env.conf \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["recycling-backend-entrypoint"]
CMD ["php-fpm"]

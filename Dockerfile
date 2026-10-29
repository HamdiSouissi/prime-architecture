FROM composer:2 AS composer-bin
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM php:8.2-fpm-bookworm AS app
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev \
    && docker-php-ext-install pdo_mysql intl zip bcmath \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
# Composer's cURL timeout follows PHP's default_socket_timeout (minimum 300 s),
# not COMPOSER_PROCESS_TIMEOUT. Keep dev packages available for tests and analysis.
ARG COMPOSER_HTTP_TIMEOUT=1200
ARG COMPOSER_MAX_PARALLEL_HTTP=4
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache,sharing=locked \
    COMPOSER_CACHE_DIR=/tmp/composer-cache COMPOSER_MAX_PARALLEL_HTTP="${COMPOSER_MAX_PARALLEL_HTTP}" \
    php -d "default_socket_timeout=${COMPOSER_HTTP_TIMEOUT}" /usr/local/bin/composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader
COPY . .
RUN mkdir -p /var/www/runtime storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache \
    && composer dump-autoload --no-interaction --optimize \
    && rm -f .env && ln -s /var/www/runtime/.env .env \
    && chown -R www-data:www-data storage bootstrap/cache /var/www/runtime
COPY --from=assets /app/public/build public/build
RUN printf '[www]\nclear_env = no\n' > /usr/local/etc/php-fpm.d/zzz-archi.conf \
    && printf 'upload_max_filesize=25M\npost_max_size=128M\nmax_file_uploads=20\n' > /usr/local/etc/php/conf.d/archi-uploads.ini
COPY docker/php/entrypoint.sh /usr/local/bin/archi-entrypoint
RUN chmod +x /usr/local/bin/archi-entrypoint
ENTRYPOINT ["archi-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

# Stage 1: build the front-end assets
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# Stage 2: PHP 8.4 with nginx
FROM serversideup/php:8.4-fpm-nginx
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_STORAGE_LINK=false

USER root
RUN install-php-extensions intl
USER www-data

WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# On start: migrate (AUTORUN) then seed demo data once. Data lives on the /data volume.
COPY --chown=www-data:www-data docker/seed.sh /etc/entrypoint.d/99-seed.sh
USER root
RUN chmod +x /etc/entrypoint.d/99-seed.sh
USER www-data

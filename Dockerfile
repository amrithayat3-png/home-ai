# Stage 1: PHP packages (the front-end build needs Flux's CSS from vendor/)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --ignore-platform-reqs

# Stage 2: build the front-end assets
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# Stage 3: PHP 8.4 with nginx
FROM serversideup/php:8.4-fpm-nginx
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_STORAGE_LINK=false

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# On start: migrate (AUTORUN) then seed demo data once. Data lives on the /data volume.
COPY --chown=www-data:www-data docker/volume.sh /etc/entrypoint.d/20-volume.sh
COPY --chown=www-data:www-data docker/seed.sh /etc/entrypoint.d/99-seed.sh
RUN chmod +x /etc/entrypoint.d/20-volume.sh /etc/entrypoint.d/99-seed.sh

# Railway mounts the volume as root. Start as root so the startup scripts can prepare /data
# (and migrate), then hand everything to www-data and run the web server as www-data.
USER root
CMD ["sh", "-c", "chown -R 33:33 /data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null; exec setpriv --reuid=33 --regid=33 --init-groups /init"]

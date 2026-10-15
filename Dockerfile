# Stage 1: PHP packages (the front-end build needs Flux's CSS from vendor/)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --ignore-platform-reqs
# S3 driver for the document bucket. Skipped once composer.lock already contains it.
RUN composer show league/flysystem-aws-s3-v3 > /dev/null 2>&1 \
    || composer require league/flysystem-aws-s3-v3:^3.0 --no-interaction --no-scripts --prefer-dist --ignore-platform-reqs --update-no-dev

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

# Stage 3: run the automated tests. If a test fails, the build fails and nothing is deployed.
# Set RUN_TESTS=0 as a service variable only for an emergency fix.
FROM serversideup/php:8.4-cli AS test
ARG RUN_TESTS=1
USER root
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && if [ "$RUN_TESTS" = "1" ]; then \
        cp .env.example .env \
        && php artisan key:generate --force \
        && composer dump-autoload \
        && php artisan test; \
    fi \
    && touch /app/.tests-passed

# Stage 4: PHP 8.4 with nginx
FROM serversideup/php:8.4-fpm-nginx
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_STORAGE_LINK=false

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/composer.json /app/composer.lock ./
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
# Ties this image to the test stage above, so it is only built when the tests passed.
COPY --from=test /app/.tests-passed /tmp/.tests-passed
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# There is no local database file on purpose: if the PostgreSQL settings are missing the app must fail loudly.
# Demo matters are no longer seeded unless SEED_DEMO_MATTERS=true. Accounts come from SEED_USERS.
COPY --chown=www-data:www-data docker/seed.sh /etc/entrypoint.d/99-seed.sh
USER root
RUN chmod +x /etc/entrypoint.d/99-seed.sh
USER www-data

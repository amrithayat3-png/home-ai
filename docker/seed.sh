#!/bin/sh
# Runs after migrations on every start. Seeders skip anything already present.
# Set SKIP_SEED=1 on services that only run a job (for example the scheduler).
if [ "${SKIP_SEED:-0}" = "1" ]; then
    exit 0
fi

mkdir -p "${FILESYSTEM_LOCAL_ROOT:-/var/www/html/storage/app/private}"
php /var/www/html/artisan db:seed --force

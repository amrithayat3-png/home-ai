#!/bin/sh
# Runs after migrations on every start. Seeders skip anything already present.
mkdir -p "${FILESYSTEM_LOCAL_ROOT:-/var/www/html/storage/app/private}"
php /var/www/html/artisan db:seed --force

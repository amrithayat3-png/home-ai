#!/bin/sh
# Runs after migrations on every start. Seeders skip anything already present.
mkdir -p "${FILESYSTEM_LOCAL_ROOT:-/data/files}"
php /var/www/html/artisan db:seed --force

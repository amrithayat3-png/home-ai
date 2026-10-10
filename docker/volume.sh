#!/bin/sh
# Runs before Laravel's automations. Railway mounts the volume as root,
# so make it writable for www-data (uid 33) and create the SQLite file.
DATA_DIR="${DB_DATABASE%/*}"
[ -z "$DATA_DIR" ] && DATA_DIR=/data

mkdir -p "$DATA_DIR" "${FILESYSTEM_LOCAL_ROOT:-$DATA_DIR/files}" 2>/dev/null || true
[ -n "$DB_DATABASE" ] && touch "$DB_DATABASE" 2>/dev/null || true
chown -R 33:33 "$DATA_DIR" 2>/dev/null || true
chmod -R u+rwX "$DATA_DIR" 2>/dev/null || true
echo "volume-setup: $(id -u):$(id -g) prepared $DATA_DIR"

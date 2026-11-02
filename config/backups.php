<?php

/*
|--------------------------------------------------------------------------
| Database backups
|--------------------------------------------------------------------------
|
| The command `php artisan db:backup` writes a full copy of the PostgreSQL
| database to a separate, private bucket. Documents are not in this copy:
| they live in the document bucket (AWS_* settings) and are protected there.
|
*/

return [

    // Which database connection to copy. Empty means the default connection.
    'connection' => env('BACKUP_CONNECTION'),

    // The filesystem disk (see config/filesystems.php) that holds the copies.
    'disk' => env('BACKUP_DISK', 'backups'),

    // Folder inside the bucket.
    'prefix' => env('BACKUP_PREFIX', 'database'),

    // Copies older than this many days are removed after a successful backup.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    // The newest copies are never removed, however old they are.
    'min_keep' => (int) env('BACKUP_MIN_KEEP', 3),

    // A copy smaller than this is treated as broken.
    'min_bytes' => (int) env('BACKUP_MIN_BYTES', 2048),

    // Seconds allowed for pg_dump and pg_restore.
    'timeout' => (int) env('BACKUP_TIMEOUT', 1800),

    // Restoring replaces data, so it is switched off unless this is true.
    'allow_restore' => (bool) env('ALLOW_DB_RESTORE', false),

    // Restore into this database instead of the live one (for a restore drill).
    'restore_url' => env('BACKUP_RESTORE_URL'),

];

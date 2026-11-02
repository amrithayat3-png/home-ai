<?php

namespace App\Console\Commands;

use App\Support\Audit;
use App\Support\BackupRetention;
use App\Support\PostgresTarget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--no-prune : Keep every old copy}';

    protected $description = 'Save a full copy of the PostgreSQL database to the private backup bucket and check that the copy can be read';

    public function handle(): int
    {
        $connection = (string) (config('backups.connection') ?: config('database.default'));
        $prefix = trim((string) config('backups.prefix'), '/');
        $name = $prefix.'/home-ai-'.now()->utc()->format('Ymd-His').'.dump';
        $temp = tempnam(sys_get_temp_dir(), 'homeai-backup-');

        try {
            $target = PostgresTarget::fromConnection($connection);
            $timeout = (int) config('backups.timeout');

            // 1. Dump the database to a temporary file (custom format, compressed).
            $dump = Process::env($target->env)->timeout($timeout)->run([
                'pg_dump', '--format=custom', '--no-owner', '--no-privileges', '--file='.$temp,
            ]);

            if (! $dump->successful()) {
                throw new RuntimeException('pg_dump failed: '.Str::limit(trim($dump->errorOutput()), 300));
            }

            clearstatcache(true, $temp);
            $size = (int) filesize($temp);

            if ($size < (int) config('backups.min_bytes')) {
                throw new RuntimeException("The copy is only {$size} bytes, which is too small to be a real backup.");
            }

            // 2. Check that the file is a readable backup before it is trusted.
            $check = Process::timeout($timeout)->run(['pg_restore', '--list', $temp]);

            if (! $check->successful()) {
                throw new RuntimeException('The copy could not be read back: '.Str::limit(trim($check->errorOutput()), 300));
            }

            // 3. Upload to the private bucket and confirm the stored size.
            $disk = Storage::disk((string) config('backups.disk'));
            $stream = fopen($temp, 'rb');

            try {
                $stored = $disk->put($name, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $stored || (int) $disk->size($name) !== $size) {
                throw new RuntimeException('The copy was not stored correctly in the backup bucket.');
            }

            // 4. Remove copies past the retention window.
            $removed = 0;

            if (! $this->option('no-prune')) {
                $names = array_map('basename', $disk->files($prefix));
                $expired = BackupRetention::expired(
                    $names,
                    now()->utc(),
                    (int) config('backups.keep_days'),
                    (int) config('backups.min_keep'),
                );

                foreach ($expired as $old) {
                    $disk->delete($prefix.'/'.$old);
                    $removed++;
                }
            }

            Audit::record('backup.created', null, [
                'bytes' => $size,
                'removed_old' => $removed,
            ], $name);

            $this->info(sprintf('Backup saved: %s (%s KB). Old copies removed: %d.', $name, number_format($size / 1024, 1), $removed));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $message = Str::limit($e->getMessage(), 300);
            $this->error('Backup FAILED: '.$message);
            Audit::record('backup.failed', null, ['error' => $message], $name);

            return self::FAILURE;
        } finally {
            if (is_string($temp) && file_exists($temp)) {
                @unlink($temp);
            }
        }
    }
}

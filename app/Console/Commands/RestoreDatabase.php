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

class RestoreDatabase extends Command
{
    protected $signature = 'db:backup:restore
        {name? : File name such as home-ai-20261101-200000.dump. Leave empty for the newest copy}
        {--force : Do not ask for confirmation}';

    protected $description = 'Replace the database with a copy from the backup bucket (needs ALLOW_DB_RESTORE=true)';

    public function handle(): int
    {
        if (! config('backups.allow_restore')) {
            $this->error('Restore is switched off. Set ALLOW_DB_RESTORE=true on this service, run the restore, then remove the setting.');

            return self::FAILURE;
        }

        $prefix = trim((string) config('backups.prefix'), '/');
        $temp = tempnam(sys_get_temp_dir(), 'homeai-restore-');

        try {
            $disk = Storage::disk((string) config('backups.disk'));
            $names = array_map('basename', $disk->files($prefix));
            $name = $this->argument('name') ?: BackupRetention::newestName($names);

            if (! $name || ! in_array($name, $names, true)) {
                throw new RuntimeException('That backup file was not found. Run php artisan db:backup:list to see what exists.');
            }

            $restoreUrl = config('backups.restore_url');
            $target = $restoreUrl
                ? PostgresTarget::fromUrl((string) $restoreUrl)
                : PostgresTarget::fromConnection((string) (config('backups.connection') ?: config('database.default')));

            $this->warn("This will REPLACE the data in database '{$target->database}' on '{$target->host}' with {$name}.");

            if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
                $this->line('Cancelled. Nothing was changed.');

                return self::SUCCESS;
            }

            $in = $disk->readStream($prefix.'/'.$name);
            $out = fopen($temp, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($out);
            if (is_resource($in)) {
                fclose($in);
            }

            // One transaction: either everything is restored or nothing is changed.
            $result = Process::env($target->env)
                ->timeout((int) config('backups.timeout'))
                ->run([
                    'pg_restore', '--clean', '--if-exists', '--no-owner', '--no-privileges',
                    '--single-transaction', '--dbname='.$target->database, $temp,
                ]);

            if (! $result->successful()) {
                throw new RuntimeException('pg_restore failed, nothing was changed: '.Str::limit(trim($result->errorOutput()), 300));
            }

            Audit::record('backup.restored', null, ['into' => $target->database], $name);
            $this->info("Restored {$name} into '{$target->database}'.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Restore FAILED: '.Str::limit($e->getMessage(), 300));

            return self::FAILURE;
        } finally {
            if (is_string($temp) && file_exists($temp)) {
                @unlink($temp);
            }
        }
    }
}

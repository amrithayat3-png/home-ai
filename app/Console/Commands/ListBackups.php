<?php

namespace App\Console\Commands;

use App\Support\BackupRetention;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ListBackups extends Command
{
    protected $signature = 'db:backup:list';

    protected $description = 'List the database copies in the backup bucket, newest first';

    public function handle(): int
    {
        $disk = Storage::disk((string) config('backups.disk'));
        $prefix = trim((string) config('backups.prefix'), '/');

        $names = array_filter(
            array_map('basename', $disk->files($prefix)),
            fn ($n) => preg_match(BackupRetention::PATTERN, $n) === 1,
        );
        rsort($names);

        if ($names === []) {
            $this->warn('There are no backups in the bucket.');

            return self::SUCCESS;
        }

        $this->table(['File', 'Size (KB)'], array_map(
            fn ($n) => [$n, number_format($disk->size($prefix.'/'.$n) / 1024, 1)],
            $names,
        ));

        return self::SUCCESS;
    }
}

<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Decides which backup files may be removed. Works from the date in the file name
 * (home-ai-20261101-200000.dump, UTC), so it behaves the same on every storage.
 */
class BackupRetention
{
    public const PATTERN = '/^home-ai-(\d{8})-(\d{6})\.dump$/';

    /**
     * @param  array<int, string>  $names  File names, without folder
     * @return array<int, string> Names to remove
     */
    public static function expired(array $names, CarbonInterface $now, int $keepDays, int $minKeep): array
    {
        $backups = [];

        foreach ($names as $name) {
            if (preg_match(self::PATTERN, $name, $m) === 1) {
                $backups[$name] = CarbonImmutable::createFromFormat('Ymd His', $m[1].' '.$m[2], 'UTC');
            }
        }

        // Newest first. Files that do not match the pattern are never touched.
        uasort($backups, fn ($a, $b) => $b <=> $a);

        $cutoff = CarbonImmutable::instance($now)->subDays($keepDays);
        $remove = [];
        $position = 0;

        foreach ($backups as $name => $takenAt) {
            $position++;

            if ($position > $minKeep && $takenAt->lessThan($cutoff)) {
                $remove[] = $name;
            }
        }

        return $remove;
    }

    public static function newestName(array $names): ?string
    {
        $valid = array_values(array_filter($names, fn ($n) => preg_match(self::PATTERN, $n) === 1));
        rsort($valid);

        return $valid[0] ?? null;
    }
}

<?php

namespace Tests\Unit;

use App\Support\BackupRetention;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BackupRetentionTest extends TestCase
{
    public function test_removes_only_copies_older_than_the_window(): void
    {
        $now = CarbonImmutable::parse('2026-11-30 12:00:00', 'UTC');

        $names = [
            'home-ai-20261129-200000.dump',
            'home-ai-20261120-200000.dump',
            'home-ai-20261110-200000.dump',
            'home-ai-20261101-200000.dump',
            'home-ai-20261015-200000.dump',
        ];

        $this->assertEqualsCanonicalizing(
            ['home-ai-20261110-200000.dump', 'home-ai-20261101-200000.dump', 'home-ai-20261015-200000.dump'],
            BackupRetention::expired($names, $now, 14, 1),
        );
    }

    public function test_the_newest_copies_are_always_kept(): void
    {
        $now = CarbonImmutable::parse('2027-06-01 12:00:00', 'UTC');

        $names = [
            'home-ai-20261001-200000.dump',
            'home-ai-20261002-200000.dump',
            'home-ai-20261003-200000.dump',
            'home-ai-20261004-200000.dump',
        ];

        $this->assertSame(
            ['home-ai-20261001-200000.dump'],
            BackupRetention::expired($names, $now, 14, 3),
        );
    }

    public function test_unrelated_files_are_never_touched(): void
    {
        $now = CarbonImmutable::parse('2027-06-01 12:00:00', 'UTC');

        $this->assertSame([], BackupRetention::expired(['notes.txt', 'home-ai-latest.dump'], $now, 14, 0));
    }

    public function test_newest_name_picks_the_latest_valid_file(): void
    {
        $this->assertSame(
            'home-ai-20261102-200000.dump',
            BackupRetention::newestName(['home-ai-20261101-200000.dump', 'junk.txt', 'home-ai-20261102-200000.dump']),
        );
        $this->assertNull(BackupRetention::newestName(['junk.txt']));
    }
}

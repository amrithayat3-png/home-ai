<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The tests run on SQLite, so a PostgreSQL connection is described here and the
        // PostgreSQL tools are faked. Nothing real is dumped or restored.
        config([
            'database.connections.pgsql.url' => 'postgresql://app_user:s3cret-pass@db.internal:5432/homeai',
            'backups.connection' => 'pgsql',
            'backups.disk' => 'backups',
            'backups.prefix' => 'database',
            'backups.min_bytes' => 100,
        ]);

        Storage::fake('backups');
    }

    /**
     * Pretend pg_dump wrote a file, and every other tool succeeded.
     */
    private function fakeTools(int $dumpBytes = 4096, int $dumpExit = 0): void
    {
        Process::fake(function (PendingProcess $process) use ($dumpBytes, $dumpExit) {
            $command = (array) $process->command;

            if (($command[0] ?? '') === 'pg_dump' && $dumpExit === 0) {
                foreach ($command as $part) {
                    if (str_starts_with($part, '--file=')) {
                        file_put_contents(substr($part, 7), str_repeat('x', $dumpBytes));
                    }
                }
            }

            return Process::result(output: '', errorOutput: $dumpExit === 0 ? '' : 'could not connect', exitCode: $dumpExit);
        });
    }

    public function test_backup_is_checked_stored_and_logged(): void
    {
        $this->fakeTools();

        $this->artisan('db:backup')->assertExitCode(0);

        $files = Storage::disk('backups')->files('database');
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('#^database/home-ai-\d{8}-\d{6}\.dump$#', $files[0]);
        $this->assertSame(4096, Storage::disk('backups')->size($files[0]));
        $this->assertTrue(AuditLog::where('action', 'backup.created')->exists());

        Process::assertRan(fn (PendingProcess $p) => ($p->command[0] ?? '') === 'pg_restore' && ($p->command[1] ?? '') === '--list');
    }

    public function test_password_is_passed_in_the_environment_not_the_command(): void
    {
        $this->fakeTools();

        $this->artisan('db:backup')->assertExitCode(0);

        Process::assertRan(function (PendingProcess $p) {
            return ($p->command[0] ?? '') === 'pg_dump'
                && ($p->environment['PGPASSWORD'] ?? null) === 's3cret-pass'
                && ($p->environment['PGHOST'] ?? null) === 'db.internal'
                && ($p->environment['PGDATABASE'] ?? null) === 'homeai'
                && ! str_contains(implode(' ', (array) $p->command), 's3cret-pass');
        });
    }

    public function test_a_failed_dump_stores_nothing_and_exits_with_an_error(): void
    {
        $this->fakeTools(dumpExit: 1);

        $this->artisan('db:backup')->assertExitCode(1);

        $this->assertSame([], Storage::disk('backups')->files('database'));
        $this->assertTrue(AuditLog::where('action', 'backup.failed')->exists());
    }

    public function test_a_suspiciously_small_copy_is_rejected(): void
    {
        $this->fakeTools(dumpBytes: 10);

        $this->artisan('db:backup')->assertExitCode(1);

        $this->assertSame([], Storage::disk('backups')->files('database'));
    }

    public function test_it_refuses_a_database_that_is_not_postgresql(): void
    {
        $this->fakeTools();
        config(['backups.connection' => 'sqlite']);

        $this->artisan('db:backup')->assertExitCode(1);

        Process::assertNothingRan();
    }

    public function test_old_copies_are_removed_but_recent_ones_stay(): void
    {
        $this->fakeTools();
        $disk = Storage::disk('backups');
        $disk->put('database/home-ai-20200101-200000.dump', 'old');
        $disk->put('database/home-ai-20200102-200000.dump', 'old');
        $disk->put('database/home-ai-20200103-200000.dump', 'old');
        $disk->put('database/home-ai-20200104-200000.dump', 'old');
        config(['backups.keep_days' => 14, 'backups.min_keep' => 3]);

        $this->artisan('db:backup')->assertExitCode(0);

        $names = array_map('basename', $disk->files('database'));
        $this->assertCount(3, $names);
        $this->assertNotContains('home-ai-20200101-200000.dump', $names);
        $this->assertNotContains('home-ai-20200102-200000.dump', $names);
    }

    public function test_restore_is_refused_unless_it_was_switched_on(): void
    {
        $this->fakeTools();
        Storage::disk('backups')->put('database/home-ai-20261101-200000.dump', str_repeat('x', 500));
        config(['backups.allow_restore' => false]);

        $this->artisan('db:backup:restore', ['--force' => true])->assertExitCode(1);

        Process::assertNothingRan();
    }

    public function test_restore_runs_in_one_transaction_with_the_newest_copy(): void
    {
        $this->fakeTools();
        $disk = Storage::disk('backups');
        $disk->put('database/home-ai-20261101-200000.dump', str_repeat('a', 500));
        $disk->put('database/home-ai-20261102-200000.dump', str_repeat('b', 500));
        config(['backups.allow_restore' => true]);

        $this->artisan('db:backup:restore', ['--force' => true])->assertExitCode(0);

        Process::assertRan(function (PendingProcess $p) {
            $command = (array) $p->command;

            return ($command[0] ?? '') === 'pg_restore'
                && in_array('--single-transaction', $command, true)
                && in_array('--dbname=homeai', $command, true);
        });
    }

    public function test_restore_reports_a_missing_file(): void
    {
        $this->fakeTools();
        config(['backups.allow_restore' => true]);

        $this->artisan('db:backup:restore', ['name' => 'home-ai-20991231-000000.dump', '--force' => true])->assertExitCode(1);

        Process::assertNothingRan();
    }
}

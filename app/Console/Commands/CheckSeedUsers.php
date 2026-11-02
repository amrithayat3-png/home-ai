<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckSeedUsers extends Command
{
    protected $signature = 'users:check-seed {--value= : Check this text instead of the SEED_USERS setting}';

    protected $description = 'Read-only check of the SEED_USERS setting against the real accounts. Prints counts only, never emails or passwords';

    public function handle(): int
    {
        $raw = $this->option('value');

        if (! is_string($raw) || $raw === '') {
            $raw = env('SEED_USERS') ?: (getenv('SEED_USERS') ?: '');
        }

        if ($raw === '') {
            $this->error('SEED_USERS is not set on this service. A rebuilt database would start with no imported accounts.');

            return self::FAILURE;
        }

        $json = base64_decode((string) $raw, true);
        $rows = $json === false ? null : json_decode($json, true);

        if (! is_array($rows)) {
            $this->error('SEED_USERS cannot be read (it is not valid base64 text holding a list). The seeder would silently import nobody.');

            return self::FAILURE;
        }

        $emails = [];
        $problems = ['no_email' => 0, 'no_password' => 0, 'bad_hash' => 0, 'bad_role' => 0, 'duplicate' => 0];
        $ready = 0;

        foreach ($rows as $row) {
            $row = is_array($row) ? $row : [];
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $password = (string) ($row['password'] ?? '');
            $ok = true;

            if ($email === '') {
                $problems['no_email']++;
                $ok = false;
            } elseif (isset($emails[$email])) {
                $problems['duplicate']++;
                $ok = false;
            } else {
                $emails[$email] = true;
            }

            if ($password === '') {
                $problems['no_password']++;
                $ok = false;
            } elseif (password_get_info($password)['algo'] === null) {
                $problems['bad_hash']++;
                $ok = false;
            }

            if (isset($row['role']) && ! array_key_exists((string) $row['role'], User::ROLES)) {
                $problems['bad_role']++;
                $ok = false;
            }

            if ($ok) {
                $ready++;
            }
        }

        $accounts = User::query()->get(['id', 'email', 'sections']);
        $inDatabase = $accounts->count();
        $missing = $accounts->filter(fn (User $u) => ! isset($emails[strtolower(trim((string) $u->email))]))->count();
        $withLimits = $accounts->filter(fn (User $u) => is_array($u->sections) && $u->sections !== [])->count();
        $notInDatabase = count(array_filter(
            array_keys($emails),
            fn ($e) => ! $accounts->contains(fn (User $u) => strtolower(trim((string) $u->email)) === $e),
        ));

        $this->info('SEED_USERS check (counts only, no emails or passwords are shown)');
        $this->line('Entries in SEED_USERS: '.count($rows));
        $this->line('Entries the seeder can import: '.$ready);
        $this->line('Problems: missing email '.$problems['no_email'].', missing password '.$problems['no_password']
            .', password not a real hash '.$problems['bad_hash'].', unknown role '.$problems['bad_role']
            .', duplicate email '.$problems['duplicate']);
        $this->line('Accounts in the database now: '.$inDatabase);
        $this->line('In the database but NOT in SEED_USERS (would be lost on a rebuild): '.$missing);
        $this->line('In SEED_USERS but not in this database: '.$notInDatabase);
        $this->line('Accounts with section limits (limits are not carried by SEED_USERS and would be lost): '.$withLimits);

        $bad = array_sum($problems) > 0;

        if ($bad || $missing > 0) {
            $this->warn('Result: NEEDS ATTENTION. Run php artisan users:export on this database and replace SEED_USERS.');
        } else {
            $this->info('Result: OK. Every account in the database is in SEED_USERS and every entry is importable.');
        }

        return self::SUCCESS;
    }
}

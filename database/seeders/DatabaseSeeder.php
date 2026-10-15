<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Safe to run on every deploy.
     *
     * Demo accounts are created only when their password is set in the
     * environment (DEMO_ADMIN_PASSWORD, DEMO_EXECUTIVE_PASSWORD).
     */
    public function run(): void
    {
        // Demo matters are for demonstrations only. Production keeps this off.
        if (filter_var(env('SEED_DEMO_MATTERS', false), FILTER_VALIDATE_BOOL)) {
            $this->call(MatterSeeder::class);
        }

        $this->importUsers(env('SEED_USERS'));

        $this->demoUser('Amrit Hayat', env('DEMO_ADMIN_EMAIL', 'admin@homeai.test'), env('DEMO_ADMIN_PASSWORD'), User::ROLE_ADMIN);
        $this->demoUser('Home Department Viewer', env('DEMO_EXECUTIVE_EMAIL', 'viewer@homeai.test'), env('DEMO_EXECUTIVE_PASSWORD'), 'executive');
    }

    /**
     * Brings existing accounts (with their password hashes) onto the host.
     * Produced locally by: php artisan users:export
     */
    private function importUsers(?string $encoded): void
    {
        if (! $encoded) {
            return;
        }

        $users = json_decode((string) base64_decode($encoded, true), true);

        if (! is_array($users)) {
            return;
        }

        foreach ($users as $row) {
            if (empty($row['email']) || empty($row['password']) || User::where('email', $row['email'])->exists()) {
                continue;
            }

            $user = new User;
            $user->forceFill([
                'name' => $row['name'] ?? $row['email'],
                'email' => $row['email'],
                'password' => $row['password'],
                'role' => $row['role'] ?? User::ROLE_OFFICER,
                'is_active' => $row['is_active'] ?? true,
                'email_verified_at' => now(),
            ])->save();
        }
    }

    private function demoUser(string $name, string $email, ?string $password, string $role): void
    {
        if (! $password || User::where('email', $email)->exists()) {
            return;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
    }
}

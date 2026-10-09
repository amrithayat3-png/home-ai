<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeUser extends Command
{
    protected $signature = 'home:make-user {email : The sign-in email} {--name= : Full name} {--role=admin : admin, officer or executive}';

    protected $description = 'Create a HOME AI account, or reset the role and password of an existing one';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $role = (string) $this->option('role');

        if (! array_key_exists($role, User::ROLES)) {
            $this->error('The role must be one of: '.implode(', ', array_keys(User::ROLES)).'.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $this->info("An account for {$email} already exists. Its role will be set to {$role} and it will be activated.");
        }

        $name = $user?->name ?? $this->option('name') ?: $this->ask('Full name');

        $password = (string) $this->secret($user
            ? 'New password (press Enter to keep the current one)'
            : 'Password (at least 10 characters, letters and numbers)');

        if ($password === '' && ! $user) {
            $this->error('A password is required for a new account.');

            return self::FAILURE;
        }

        if ($password !== '') {
            if (strlen($password) < 10 || ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
                $this->error('The password needs at least 10 characters, with letters and numbers.');

                return self::FAILURE;
            }

            if ($password !== (string) $this->secret('Confirm password')) {
                $this->error('The passwords did not match.');

                return self::FAILURE;
            }
        }

        $user ??= new User();

        $attributes = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'is_active' => true,
        ];

        if (! $user->exists) {
            $attributes['email_verified_at'] = now();
        }

        if ($password !== '') {
            $attributes['password'] = $password;
        }

        $user->forceFill($attributes)->save();

        $this->info("Done. {$email} can now sign in as ".User::ROLES[$role].'.');

        return self::SUCCESS;
    }
}

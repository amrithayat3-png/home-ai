<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ExportUsers extends Command
{
    protected $signature = 'users:export';

    protected $description = 'Print all users (with password hashes, never plain passwords) as one text value for the SEED_USERS variable on the host';

    public function handle(): int
    {
        $users = User::query()->orderBy('id')->get()->map(fn (User $user) => [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
        ])->all();

        $this->line(base64_encode(json_encode($users, JSON_UNESCAPED_UNICODE)));
        $this->newLine();
        $this->warn('Paste the line above as the SEED_USERS variable on the host. Do not commit it or share it.');

        return self::SUCCESS;
    }
}

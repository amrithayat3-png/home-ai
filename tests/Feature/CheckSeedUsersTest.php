<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CheckSeedUsersTest extends TestCase
{
    use RefreshDatabase;

    private function encode(array $rows): string
    {
        return base64_encode(json_encode($rows));
    }

    private function rowFor(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
            'role' => $user->role,
            'is_active' => true,
        ];
    }

    private function run_check(string $value): array
    {
        $code = Artisan::call('users:check-seed', ['--value' => $value]);

        return [$code, Artisan::output()];
    }

    public function test_reports_ok_when_every_account_is_in_the_list(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        [$code, $output] = $this->run_check($this->encode([$this->rowFor($a), $this->rowFor($b)]));

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Entries the seeder can import: 2', $output);
        $this->assertStringContainsString('NOT in SEED_USERS (would be lost on a rebuild): 0', $output);
        $this->assertStringContainsString('Result: OK', $output);
    }

    public function test_flags_accounts_missing_from_the_list(): void
    {
        $a = User::factory()->create();
        User::factory()->create();

        [, $output] = $this->run_check($this->encode([$this->rowFor($a)]));

        $this->assertStringContainsString('NOT in SEED_USERS (would be lost on a rebuild): 1', $output);
        $this->assertStringContainsString('NEEDS ATTENTION', $output);
    }

    public function test_flags_plain_passwords_missing_emails_and_duplicates(): void
    {
        $a = User::factory()->create();
        $row = $this->rowFor($a);

        [, $output] = $this->run_check($this->encode([
            $row,
            $row,
            ['email' => 'plain@example.test', 'password' => 'not-a-hash', 'role' => 'officer'],
            ['email' => '', 'password' => $a->password],
        ]));

        $this->assertStringContainsString('password not a real hash 1', $output);
        $this->assertStringContainsString('missing email 1', $output);
        $this->assertStringContainsString('duplicate email 1', $output);
        $this->assertStringContainsString('NEEDS ATTENTION', $output);
    }

    public function test_unreadable_value_fails(): void
    {
        [$code, $output] = $this->run_check('this is not base64 json');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('cannot be read', $output);
    }

    public function test_output_never_contains_emails_or_password_hashes(): void
    {
        $a = User::factory()->create(['email' => 'secret.person@example.test']);

        [, $output] = $this->run_check($this->encode([$this->rowFor($a)]));

        $this->assertStringNotContainsString('secret.person@example.test', $output);
        $this->assertStringNotContainsString($a->password, $output);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Matter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The name and seal of the Sindh Center For Excellence on Countering Violent Extremism
 * appear on every screen, spelled exactly as the centre writes it.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'Sindh Center For Excellence on Countering Violent Extremism';

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true])->save();

        return $user;
    }

    private function assertBranded($response): void
    {
        $response->assertOk()
            ->assertSee(self::NAME)
            ->assertSee('images/centre-seal.png', false);
    }

    public function test_the_seal_file_is_in_place(): void
    {
        $this->assertFileExists(public_path('images/centre-seal.png'));
        $this->assertFileExists(public_path('images/centre-seal-64.png'));
    }

    public function test_the_sign_in_page_carries_the_name_and_seal(): void
    {
        $this->assertBranded($this->get(route('login')));
    }

    public function test_every_signed_in_screen_carries_the_name_and_seal(): void
    {
        $matter = Matter::forceCreate([
            'matter_reference' => 'HM-L-4242', 'title' => 'Brand', 'section' => 'Law', 'category' => 'Letter',
            'priority' => 'Normal', 'status' => 'Open', 'deadline' => '2026-12-15',
        ]);
        $this->actingAs($this->user(User::ROLE_ADMIN));

        foreach ([
            route('home'),
            route('matters'),
            route('matters.show', $matter),
            route('matters.create'),
            route('documents.index'),
            route('documents.create'),
            route('search', ['q' => 'brand']),
            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.audit.index'),
        ] as $url) {
            $this->assertBranded($this->get($url));
        }
    }
}

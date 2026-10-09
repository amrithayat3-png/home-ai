<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
    }

    public function test_signed_in_users_see_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('home'));

        $response->assertOk();
    }
}

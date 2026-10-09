<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, bool $active = true): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => $active])->save();

        return $user;
    }

    public function test_guests_cannot_see_matters_or_documents(): void
    {
        $this->get(route('matters'))->assertRedirect(route('login'));
        $this->get(route('documents.index'))->assertRedirect(route('login'));
        $this->post(route('ask'), ['question' => 'test'])->assertRedirect(route('login'));
    }

    public function test_executives_can_view_but_not_change_anything(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_EXECUTIVE));

        $this->get(route('matters'))->assertOk();
        $this->get(route('documents.index'))->assertOk();

        $this->get(route('matters.create'))->assertForbidden();
        $this->get(route('documents.create'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_officers_can_work_but_not_manage_accounts(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OFFICER));

        $this->get(route('matters.create'))->assertOk();
        $this->get(route('documents.create'))->assertOk();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_administrators_can_manage_accounts(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_ADMIN));

        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.users.create'))->assertOk();
    }

    public function test_deactivated_users_are_signed_out(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OFFICER, active: false));

        $this->get(route('matters'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }
}

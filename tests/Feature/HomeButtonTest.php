<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every screen has a Home button, and every screen has a way to search.
 */
class HomeButtonTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true])->save();

        return $user;
    }

    private function matter(): Matter
    {
        return Matter::forceCreate([
            'matter_reference' => 'HM-L-9090',
            'title' => 'Nav test',
            'section' => 'Law',
            'category' => 'Letter',
            'priority' => 'Normal',
            'status' => 'Open',
            'deadline' => '2026-12-15',
        ]);
    }

    private function document(): Document
    {
        return Document::create([
            'original_name' => 'nav.pdf',
            'stored_path' => 'documents/x/nav.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'category' => 'letter',
            'review_status' => 'Needs review',
        ]);
    }

    private function assertHasStrip($response): void
    {
        $response->assertOk()
            ->assertSee('class="quicknav"', false)
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('action="'.route('search').'"', false);
    }

    public function test_officer_screens_have_the_home_button_and_search(): void
    {
        $matter = $this->matter();
        $document = $this->document();
        $this->actingAs($this->user(User::ROLE_OFFICER));

        $this->assertHasStrip($this->get(route('matters')));
        $this->assertHasStrip($this->get(route('matters.show', $matter)));
        $this->assertHasStrip($this->get(route('matters.create')));
        $this->assertHasStrip($this->get(route('documents.index')));
        $this->assertHasStrip($this->get(route('documents.create')));
        $this->assertHasStrip($this->get(route('documents.show', $document)));
        $this->assertHasStrip($this->get(route('search', ['q' => 'nav'])));
    }

    public function test_admin_screens_have_the_home_button_and_search(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN));

        $this->assertHasStrip($this->get(route('admin.users.index')));
        $this->assertHasStrip($this->get(route('admin.users.create')));
        $this->assertHasStrip($this->get(route('admin.audit.index')));
    }

    public function test_executives_see_the_strip_without_the_upload_link(): void
    {
        $this->actingAs($this->user(User::ROLE_EXECUTIVE));

        $this->assertHasStrip($this->get(route('matters')));
        $this->get(route('matters'))->assertDontSee(route('documents.create'), false);
    }

    public function test_the_main_screen_has_a_search_box(): void
    {
        $this->actingAs($this->user(User::ROLE_OFFICER));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('action="'.route('search').'"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Matter;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterCloseDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true])->save();

        return $user;
    }

    private function matter(string $status = 'Open'): Matter
    {
        return Matter::forceCreate([
            'matter_reference' => 'HM-D-'.random_int(1000, 9999),
            'title' => 'Delete test',
            'section' => 'Law',
            'category' => 'Letter',
            'priority' => 'Normal',
            'status' => $status,
            'deadline' => '2026-10-15',
        ]);
    }

    private function document(Matter $matter): Document
    {
        return Document::create([
            'original_name' => 'a.pdf',
            'stored_path' => 'documents/x/a.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'category' => 'letter',
            'review_status' => 'classified',
            'matter_id' => $matter->id,
            'matter_link_source' => 'manual',
        ]);
    }

    public function test_officer_can_close_and_reopen(): void
    {
        $matter = $this->matter();
        $this->actingAs($this->userWithRole(User::ROLE_OFFICER));

        $this->patch(route('matters.close', $matter))->assertRedirect();
        $this->assertSame('Closed', $matter->fresh()->status);

        $this->patch(route('matters.reopen', $matter))->assertRedirect();
        $this->assertSame('Open', $matter->fresh()->status);
    }

    public function test_executive_cannot_close_or_delete(): void
    {
        $matter = $this->matter();
        $this->actingAs($this->userWithRole(User::ROLE_EXECUTIVE));

        $this->patch(route('matters.close', $matter))->assertForbidden();
        $this->delete(route('matters.destroy', $matter))->assertForbidden();
        $this->assertSame('Open', $matter->fresh()->status);
    }

    public function test_officer_cannot_delete(): void
    {
        $matter = $this->matter('Closed');
        $this->actingAs($this->userWithRole(User::ROLE_OFFICER));

        $this->delete(route('matters.destroy', $matter))->assertForbidden();
        $this->assertNotNull(Matter::find($matter->id));
    }

    public function test_admin_cannot_delete_open_matter_with_documents(): void
    {
        $matter = $this->matter('Open');
        $this->document($matter);
        $this->actingAs($this->userWithRole(User::ROLE_ADMIN));

        $this->delete(route('matters.destroy', $matter))->assertRedirect(route('matters.show', $matter));
        $this->assertNotNull(Matter::find($matter->id));
    }

    public function test_admin_deletes_closed_matter_and_documents_are_kept_unlinked(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $matter = $this->matter('Closed');
        $document = $this->document($matter);
        Reminder::create([
            'matter_id' => $matter->id,
            'user_id' => $admin->id,
            'stage' => 'due',
            'deadline' => '2026-10-15',
            'message' => 'x',
        ]);

        $this->actingAs($admin)->delete(route('matters.destroy', $matter))->assertRedirect(route('matters'));

        $this->assertNull(Matter::find($matter->id));
        $this->assertSame(0, Reminder::count());
        $fresh = $document->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->matter_id);
        $this->assertNull($fresh->matter_link_source);
    }

    public function test_admin_can_delete_open_matter_without_documents(): void
    {
        $matter = $this->matter('Open');
        $this->actingAs($this->userWithRole(User::ROLE_ADMIN))->delete(route('matters.destroy', $matter));

        $this->assertNull(Matter::find($matter->id));
    }

    public function test_closed_filter_lists_closed_matters_only(): void
    {
        $open = $this->matter('Open');
        $closed = $this->matter('Closed');
        $this->actingAs($this->userWithRole(User::ROLE_OFFICER));

        $this->get(route('matters', ['status' => 'Closed']))
            ->assertOk()
            ->assertSee($closed->matter_reference)
            ->assertDontSee($open->matter_reference);

        $this->get(route('matters'))
            ->assertOk()
            ->assertSee($open->matter_reference)
            ->assertDontSee($closed->matter_reference);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, ?array $sections = null): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'sections' => $sections])->save();

        return $user;
    }

    private function fakeScanner(string $category = 'bills', float $confidence = 0.99): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'category' => $category,
                        'confidence' => $confidence,
                        'reason' => 'Looks like a bill.',
                        'matter_reference' => null,
                        'subject' => 'Electricity bill',
                        'extracted_text' => 'Electricity bill for September.',
                    ])]]],
                ]],
            ]),
        ]);
    }

    public function test_an_upload_is_stored_hashed_and_waits_for_human_review(): void
    {
        Storage::fake('local');
        $this->fakeScanner(confidence: 0.99);

        $officer = $this->userWithRole(User::ROLE_OFFICER);
        $this->actingAs($officer);

        $this->post(route('documents.store'), [
            'document' => UploadedFile::fake()->createWithContent('bill.pdf', 'first file bytes'),
        ])->assertRedirect(route('documents.index'));

        $document = Document::firstOrFail();

        Storage::disk('local')->assertExists($document->stored_path);
        $this->assertSame(hash('sha256', 'first file bytes'), $document->file_hash);
        $this->assertSame($officer->id, $document->uploaded_by);
        $this->assertSame('Needs review', $document->review_status, 'nothing is filed without a person confirming it');
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.uploaded', 'user_id' => $officer->id]);
    }

    public function test_auto_filing_is_off_unless_the_department_turns_it_on(): void
    {
        Storage::fake('local');
        $this->fakeScanner(confidence: 0.99);
        config(['documents.auto_file' => true, 'documents.auto_file_threshold' => 0.95]);

        $this->actingAs($this->userWithRole(User::ROLE_OFFICER));

        $this->post(route('documents.store'), [
            'document' => UploadedFile::fake()->createWithContent('bill.pdf', 'auto file bytes'),
        ]);

        $this->assertSame('Filed', Document::firstOrFail()->review_status);
    }

    public function test_the_same_file_cannot_be_filed_twice(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->userWithRole(User::ROLE_OFFICER));

        $this->post(route('documents.store'), [
            'document' => UploadedFile::fake()->createWithContent('bill.pdf', 'identical bytes'),
        ]);

        $this->post(route('documents.store'), [
            'document' => UploadedFile::fake()->createWithContent('copy of bill.pdf', 'identical bytes'),
        ])->assertSessionHasErrors('document');

        $this->assertSame(1, Document::count());
        $this->assertCount(1, Storage::disk('local')->allFiles('documents'), 'no second copy is left in storage');
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.duplicate_blocked']);
        Http::assertSentCount(1);
    }

    public function test_a_failed_scan_leaves_no_orphan_file(): void
    {
        Storage::fake('local');
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('boom', 500)]);

        $this->actingAs($this->userWithRole(User::ROLE_OFFICER));

        $this->post(route('documents.store'), [
            'document' => UploadedFile::fake()->createWithContent('bill.pdf', 'will fail'),
        ])->assertSessionHasErrors('document');

        $this->assertSame(0, Document::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_opening_a_file_is_recorded_in_the_audit_trail(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/bills/a.pdf', 'pdf bytes');

        $document = Document::create([
            'original_name' => 'a.pdf',
            'stored_path' => 'documents/bills/a.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 9,
            'category' => 'bills',
            'review_status' => 'Needs review',
        ]);

        $this->actingAs($this->userWithRole(User::ROLE_EXECUTIVE));

        $this->get(route('documents.file', $document))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'document.opened', 'subject_id' => $document->id]);
    }

    public function test_an_account_limited_to_one_section_cannot_see_another_sections_documents(): void
    {
        $prison = Matter::forceCreate([
            'matter_reference' => 'HM-2026-050', 'title' => 'Prison matter', 'section' => 'Prison',
            'category' => 'Letter', 'priority' => 'Normal', 'status' => 'Open', 'deadline' => '2026-12-01',
        ]);

        $police = Matter::forceCreate([
            'matter_reference' => 'HM-2026-051', 'title' => 'Police matter', 'section' => 'Police',
            'category' => 'Letter', 'priority' => 'Normal', 'status' => 'Open', 'deadline' => '2026-12-01',
        ]);

        $make = fn (Matter $matter) => Document::create([
            'original_name' => $matter->section.'.pdf', 'stored_path' => 'documents/x/'.$matter->section.'.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 1, 'category' => 'letters',
            'review_status' => 'Filed', 'matter_id' => $matter->id,
        ]);

        $prisonDoc = $make($prison);
        $policeDoc = $make($police);
        $unlinked = Document::create([
            'original_name' => 'loose.pdf', 'stored_path' => 'documents/x/loose.pdf', 'mime_type' => 'application/pdf',
            'file_size' => 1, 'category' => 'letters', 'review_status' => 'Needs review',
        ]);

        $this->actingAs($this->userWithRole(User::ROLE_OFFICER, ['Police']));

        $this->get(route('documents.show', $policeDoc))->assertOk();
        $this->get(route('documents.show', $prisonDoc))->assertNotFound();
        $this->get(route('documents.show', $unlinked))->assertNotFound();
        $this->get(route('matters.show', $prison))->assertNotFound();

        $this->patch(route('documents.matter', $policeDoc), ['matter_id' => $prison->id])->assertSessionHasErrors('matter_id');

        $listed = $this->get(route('documents.index'))->assertOk()->viewData('documents');
        $this->assertSame([$policeDoc->id], $listed->pluck('id')->all());
    }

    public function test_unrestricted_accounts_and_administrators_see_everything(): void
    {
        $matter = Matter::forceCreate([
            'matter_reference' => 'HM-2026-060', 'title' => 'Any', 'section' => 'Prison',
            'category' => 'Letter', 'priority' => 'Normal', 'status' => 'Open', 'deadline' => '2026-12-01',
        ]);

        $this->actingAs($this->userWithRole(User::ROLE_OFFICER));
        $this->get(route('matters.show', $matter))->assertOk();

        $this->actingAs($this->userWithRole(User::ROLE_ADMIN, ['Police']));
        $this->get(route('matters.show', $matter))->assertOk();
    }

    public function test_audit_entries_cannot_be_changed_or_deleted(): void
    {
        $log = AuditLog::create(['action' => 'test.action', 'created_at' => now()]);

        $this->assertFalse($log->update(['action' => 'changed']));
        $this->assertFalse($log->delete());
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'test.action']);
    }

    public function test_only_administrators_can_read_the_audit_log(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OFFICER));
        $this->get(route('admin.audit.index'))->assertForbidden();

        $this->actingAs($this->userWithRole(User::ROLE_ADMIN));
        $this->get(route('admin.audit.index'))->assertOk();
    }

    public function test_deleting_a_matter_keeps_a_copy_in_the_audit_trail(): void
    {
        $matter = Matter::forceCreate([
            'matter_reference' => 'HM-2026-070', 'title' => 'To be deleted', 'section' => 'Police',
            'category' => 'Letter', 'priority' => 'Normal', 'status' => 'Closed', 'deadline' => '2026-12-01',
        ]);

        $this->actingAs($this->userWithRole(User::ROLE_ADMIN));
        $this->delete(route('matters.destroy', $matter))->assertRedirect();

        $this->assertDatabaseMissing('matters', ['id' => $matter->id]);

        $entry = AuditLog::where('action', 'matter.deleted')->firstOrFail();
        $this->assertSame('To be deleted', $entry->details['snapshot']['title']);
        $this->assertSame('HM-2026-070', $entry->subject_label);
    }
}

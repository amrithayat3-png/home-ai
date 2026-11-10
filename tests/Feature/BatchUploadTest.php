<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Many files at once: each file is sent on its own and answered as JSON, then the officer can
 * confirm everything the scanner is very sure about with one button.
 */
class BatchUploadTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'incoming/5a1f4c3e-6f55-4f3a-9a52-2c3f8a6d1e77.pdf';

    private function user(string $role = User::ROLE_OFFICER, ?array $sections = null): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'sections' => $sections])->save();

        return $user;
    }

    private function fakeScanner(float $confidence = 0.95, ?string $reference = null): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'category' => 'correspondence',
                        'confidence' => $confidence,
                        'reason' => 'A letter.',
                        'matter_reference' => $reference,
                        'subject' => 'Status request',
                        'extracted_text' => 'Please confirm the status.',
                    ])]]],
                ]],
            ]),
        ]);
    }

    private function pdf(string $name, string $tag): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n% {$tag}\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n"
        );
    }

    private function matter(string $section = 'Law'): Matter
    {
        return Matter::forceCreate([
            'matter_reference' => 'HM-L-'.random_int(1000, 9999),
            'title' => 'Batch test',
            'section' => $section,
            'category' => 'Letter',
            'priority' => 'Normal',
            'status' => 'Open',
            'deadline' => '2026-12-15',
        ]);
    }

    private function waitingDocument(float $confidence, string $name = 'x.pdf', ?int $uploader = null, ?Matter $matter = null): Document
    {
        return Document::create([
            'original_name' => $name,
            'stored_path' => 'documents/correspondence/'.$name,
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'category' => 'correspondence',
            'confidence' => $confidence,
            'review_status' => 'Needs review',
            'matter_id' => $matter?->id,
            'uploaded_by' => $uploader,
        ]);
    }

    public function test_one_file_is_scanned_and_answered_as_json(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $officer = $this->user();
        $this->actingAs($officer);

        $this->postJson(route('documents.upload-item'), ['document' => $this->pdf('one.pdf', 'one')])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'name' => 'one.pdf',
                'category' => 'Correspondence',
                'review_status' => 'Needs review',
            ])
            ->assertJsonStructure(['id', 'confidence', 'url', 'matter', 'suggested_matter', 'message']);

        $document = Document::firstOrFail();
        $this->assertSame($officer->id, $document->uploaded_by);
        Storage::disk('local')->assertExists($document->stored_path);
    }

    public function test_many_files_can_be_sent_one_after_another(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->user());

        foreach (range(1, 5) as $i) {
            $this->postJson(route('documents.upload-item'), ['document' => $this->pdf("scan-{$i}.pdf", "tag{$i}")])->assertOk();
        }

        $this->assertSame(5, Document::count());
    }

    public function test_the_same_file_twice_is_refused_with_a_message_and_not_stored_again(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->user());

        $this->postJson(route('documents.upload-item'), ['document' => $this->pdf('a.pdf', 'same')])->assertOk();

        $this->postJson(route('documents.upload-item'), ['document' => $this->pdf('b.pdf', 'same')])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(1, Document::count());
    }

    public function test_a_bad_file_type_is_refused_but_does_not_stop_the_next_file(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->user());

        $this->postJson(route('documents.upload-item'), ['document' => UploadedFile::fake()->createWithContent('virus.exe', 'MZ')])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->postJson(route('documents.upload-item'), ['document' => $this->pdf('good.pdf', 'good')])->assertOk();

        $this->assertSame(1, Document::count());
    }

    public function test_a_file_that_is_not_really_a_pdf_is_refused(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->user());

        $this->postJson(route('documents.upload-item'), ['document' => UploadedFile::fake()->createWithContent('fake.pdf', 'just text, not a pdf')])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(0, Document::count());
    }

    public function test_a_file_already_in_storage_can_be_filed_through_the_json_route(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->user());

        Storage::disk('local')->put(self::KEY, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        $this->withSession(['direct_uploads' => [self::KEY]])
            ->postJson(route('documents.upload-item'), ['uploaded_key' => self::KEY, 'uploaded_name' => 'Big.pdf'])
            ->assertOk()
            ->assertJson(['ok' => true, 'name' => 'Big.pdf']);

        Storage::disk('local')->assertMissing(self::KEY);
    }

    public function test_a_storage_link_that_was_not_issued_to_this_session_is_refused_as_json(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->user());

        Storage::disk('local')->put(self::KEY, '%PDF-1.4');

        $this->postJson(route('documents.upload-item'), ['uploaded_key' => self::KEY, 'uploaded_name' => 'x.pdf'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(0, Document::count());
    }

    public function test_the_chosen_matter_is_linked_to_the_document(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->user());
        $matter = $this->matter();

        $this->postJson(route('documents.upload-item'), [
            'document' => $this->pdf('m.pdf', 'm'),
            'matter_id' => $matter->id,
        ])->assertOk()->assertJson(['matter' => $matter->matter_reference]);

        $this->assertSame($matter->id, Document::firstOrFail()->matter_id);
    }

    public function test_a_matter_outside_the_accounts_sections_cannot_be_chosen(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $police = $this->matter('Police');
        $this->actingAs($this->user(User::ROLE_OFFICER, ['Law']));

        $this->postJson(route('documents.upload-item'), [
            'document' => $this->pdf('n.pdf', 'n'),
            'matter_id' => $police->id,
        ])->assertStatus(422)->assertJson(['ok' => false]);

        $this->assertSame(0, Document::count());
    }

    public function test_guests_and_executives_cannot_upload_or_confirm(): void
    {
        $this->postJson(route('documents.upload-item'))->assertUnauthorized();
        $this->postJson(route('documents.confirm-bulk'))->assertUnauthorized();

        $this->actingAs($this->user(User::ROLE_EXECUTIVE));

        $this->postJson(route('documents.upload-item'))->assertForbidden();
        $this->postJson(route('documents.confirm-bulk'))->assertForbidden();
    }

    public function test_confirm_all_files_only_the_documents_the_scanner_is_sure_about(): void
    {
        $officer = $this->user();
        $this->actingAs($officer);

        $sure = $this->waitingDocument(0.95, 'sure.pdf');
        $exact = $this->waitingDocument(0.90, 'exact.pdf');
        $unsure = $this->waitingDocument(0.60, 'unsure.pdf');

        $this->postJson(route('documents.confirm-bulk'))
            ->assertOk()
            ->assertJson(['ok' => true, 'confirmed' => 2, 'remaining' => 1]);

        $this->assertSame('Filed', $sure->fresh()->review_status);
        $this->assertSame('Filed', $exact->fresh()->review_status);
        $this->assertSame('Needs review', $unsure->fresh()->review_status);
    }

    public function test_confirm_all_can_be_limited_to_one_batch(): void
    {
        $this->actingAs($this->user());

        $mine = $this->waitingDocument(0.95, 'mine.pdf');
        $other = $this->waitingDocument(0.95, 'other.pdf');

        $this->postJson(route('documents.confirm-bulk'), ['ids' => [$mine->id]])
            ->assertOk()
            ->assertJson(['confirmed' => 1]);

        $this->assertSame('Filed', $mine->fresh()->review_status);
        $this->assertSame('Needs review', $other->fresh()->review_status);
    }

    public function test_confirm_all_leaves_already_filed_documents_alone_and_records_each_in_the_audit_trail(): void
    {
        $officer = $this->user();
        $this->actingAs($officer);

        $done = $this->waitingDocument(0.99, 'done.pdf');
        $done->forceFill(['review_status' => 'Filed'])->save();
        $waiting = $this->waitingDocument(0.99, 'waiting.pdf');

        $this->postJson(route('documents.confirm-bulk'))->assertJson(['confirmed' => 1]);

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.confirmed', 'user_id' => $officer->id]);
        $this->assertStringContainsString('"bulk":true', (string) DB::table('audit_logs')->value('details'));
        $this->assertSame('Filed', $waiting->fresh()->review_status);
    }

    public function test_confirm_all_by_a_plain_form_redirects_back_to_the_list_with_a_message(): void
    {
        $this->actingAs($this->user());
        $this->waitingDocument(0.95);

        $this->post(route('documents.confirm-bulk'))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHas('success');
    }

    public function test_confirm_all_never_touches_documents_the_account_cannot_see(): void
    {
        $police = $this->matter('Police');
        $hidden = $this->waitingDocument(0.99, 'hidden.pdf', null, $police);
        $this->actingAs($this->user(User::ROLE_OFFICER, ['Law']));

        $this->postJson(route('documents.confirm-bulk'))->assertJson(['confirmed' => 0]);

        $this->assertSame('Needs review', Document::withoutGlobalScopes()->find($hidden->id)->review_status);
    }

    public function test_the_documents_list_offers_confirm_all_and_a_needs_review_tab(): void
    {
        $this->actingAs($this->user());
        $this->waitingDocument(0.95, 'sure.pdf');
        $this->waitingDocument(0.50, 'unsure.pdf');

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertSee(route('documents.confirm-bulk'), false);

        $this->get(route('documents.index', ['status' => 'review']))
            ->assertOk()
            ->assertSee('sure.pdf');
    }

    public function test_the_upload_page_can_start_from_a_matter(): void
    {
        $matter = $this->matter();
        $this->actingAs($this->user());

        $this->get(route('documents.create', ['matter' => $matter->id]))
            ->assertOk()
            ->assertSee('These documents will be linked to', false)
            ->assertSee($matter->matter_reference)
            ->assertSee('Back to '.$matter->matter_reference);

        $this->get(route('documents.create'))
            ->assertOk()
            ->assertDontSee('These documents will be linked to', false);
    }

    public function test_the_upload_page_ignores_a_matter_the_account_cannot_see(): void
    {
        $police = $this->matter('Police');
        $this->actingAs($this->user(User::ROLE_OFFICER, ['Law']));

        $this->get(route('documents.create', ['matter' => $police->id]))
            ->assertOk()
            ->assertDontSee($police->matter_reference);
    }

    public function test_the_matter_page_has_an_add_documents_button_for_officers_only(): void
    {
        $matter = $this->matter();

        $this->actingAs($this->user());
        $this->get(route('matters.show', $matter))
            ->assertOk()
            ->assertSee(route('documents.create', ['matter' => $matter->id]), false);

        $this->actingAs($this->user(User::ROLE_EXECUTIVE));
        $this->get(route('matters.show', $matter))
            ->assertOk()
            ->assertDontSee(route('documents.create', ['matter' => $matter->id]), false);
    }
}

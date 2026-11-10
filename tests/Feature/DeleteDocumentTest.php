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

class DeleteDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = User::ROLE_OFFICER, ?array $sections = null): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'sections' => $sections])->save();

        return $user;
    }

    private function matter(string $section = 'Law'): Matter
    {
        return Matter::forceCreate([
            'matter_reference' => 'HM-L-'.random_int(1000, 9999),
            'title' => 'Delete doc test',
            'section' => $section,
            'category' => 'Letter',
            'priority' => 'Normal',
            'status' => 'Open',
            'deadline' => '2026-12-15',
        ]);
    }

    private function storedDocument(?Matter $matter = null, string $path = 'documents/correspondence/a.pdf'): Document
    {
        Storage::disk('local')->put($path, 'file bytes');

        return Document::create([
            'original_name' => 'wrong upload.pdf',
            'stored_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'category' => 'correspondence',
            'confidence' => 0.9,
            'review_status' => 'Needs review',
            'matter_id' => $matter?->id,
            'file_hash' => hash('sha256', 'file bytes'),
        ]);
    }

    public function test_an_officer_can_delete_a_document_and_its_file(): void
    {
        Storage::fake('local');
        $officer = $this->user();
        $this->actingAs($officer);
        $document = $this->storedDocument();

        $this->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing('documents/correspondence/a.pdf');
    }

    public function test_the_deletion_is_recorded_in_the_audit_trail(): void
    {
        Storage::fake('local');
        $officer = $this->user();
        $this->actingAs($officer);
        $document = $this->storedDocument();

        $this->delete(route('documents.destroy', $document));

        $log = AuditLog::where('action', 'document.deleted')->firstOrFail();
        $this->assertSame($officer->id, $log->user_id);
        $this->assertSame('wrong upload.pdf', $log->subject_label);
        $this->assertSame('wrong upload.pdf', $log->details['snapshot']['original_name']);
    }

    public function test_after_deleting_a_matters_document_you_return_to_that_matter(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user());
        $matter = $this->matter();
        $document = $this->storedDocument($matter);

        $this->delete(route('documents.destroy', $document))
            ->assertRedirect(route('matters.show', $matter));
    }

    public function test_a_document_whose_file_is_already_gone_can_still_be_deleted(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user());
        $document = $this->storedDocument();
        Storage::disk('local')->delete($document->stored_path);

        $this->delete(route('documents.destroy', $document))->assertRedirect(route('documents.index'));

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_executives_and_guests_cannot_delete(): void
    {
        Storage::fake('local');
        $document = $this->storedDocument();

        $this->delete(route('documents.destroy', $document))->assertRedirect(route('login'));

        $this->actingAs($this->user(User::ROLE_EXECUTIVE));
        $this->delete(route('documents.destroy', $document))->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->stored_path);
    }

    public function test_an_account_cannot_delete_documents_outside_its_sections(): void
    {
        Storage::fake('local');
        $police = $this->matter('Police');
        $document = $this->storedDocument($police);

        $this->actingAs($this->user(User::ROLE_OFFICER, ['Law']));
        $this->delete(route('documents.destroy', $document))->assertNotFound();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->stored_path);
    }

    public function test_the_delete_button_shows_for_officers_but_not_executives(): void
    {
        Storage::fake('local');
        $document = $this->storedDocument();

        $this->actingAs($this->user());
        $this->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Delete document')
            ->assertSee(route('documents.destroy', $document), false);

        $this->actingAs($this->user(User::ROLE_EXECUTIVE));
        $this->get(route('documents.show', $document))
            ->assertOk()
            ->assertDontSee('Delete document');
    }

    public function test_the_same_file_can_be_uploaded_again_after_it_was_deleted(): void
    {
        Storage::fake('local');
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'category' => 'correspondence', 'confidence' => 0.9, 'reason' => 'A letter.',
                        'matter_reference' => null, 'subject' => 'S', 'extracted_text' => 'Text.',
                    ])]]],
                ]],
            ]),
        ]);
        $this->actingAs($this->user());

        $pdf = fn () => UploadedFile::fake()->createWithContent('again.pdf', "%PDF-1.4\n% same bytes\n%%EOF\n");

        $this->postJson(route('documents.upload-item'), ['document' => $pdf()])->assertOk();
        $this->delete(route('documents.destroy', Document::firstOrFail()));
        $this->postJson(route('documents.upload-item'), ['document' => $pdf()])->assertOk();

        $this->assertSame(1, Document::count());
    }
}

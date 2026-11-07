<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Large files (over about 4 MB) go from the browser straight to storage, then the app
 * checks, classifies and files them. These tests cover the app's half of that.
 */
class DirectUploadTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'incoming/0b9d3c3e-6f55-4f3a-9a52-2c3f8a6d1e10.pdf';

    private function officer(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => User::ROLE_OFFICER, 'is_active' => true, 'sections' => null])->save();

        return $user;
    }

    private function fakeScanner(): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'category' => 'correspondence',
                        'confidence' => 0.9,
                        'reason' => 'A letter.',
                        'matter_reference' => null,
                        'subject' => 'Status request',
                        'extracted_text' => 'Please confirm the status.',
                    ])]]],
                ]],
            ]),
        ]);
    }

    private function pdfBytes(string $tag = 'one'): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n% {$tag}\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    public function test_the_upload_link_endpoint_falls_back_to_the_form_on_a_server_disk(): void
    {
        Storage::fake('local');
        $this->actingAs($this->officer());

        $this->postJson(route('documents.direct-url'), ['filename' => 'letter.pdf', 'size' => 6_000_000])
            ->assertOk()
            ->assertJson(['direct' => false]);
    }

    public function test_the_upload_link_endpoint_rejects_bad_types_and_sizes(): void
    {
        Storage::fake('local');
        $this->actingAs($this->officer());

        $this->postJson(route('documents.direct-url'), ['filename' => 'virus.exe', 'size' => 6_000_000])
            ->assertStatus(422);

        $this->postJson(route('documents.direct-url'), ['filename' => 'huge.pdf', 'size' => 11 * 1024 * 1024])
            ->assertStatus(422);
    }

    public function test_the_upload_link_endpoint_is_closed_to_executives_and_guests(): void
    {
        $this->postJson(route('documents.direct-url'), ['filename' => 'a.pdf', 'size' => 5_000_000])->assertUnauthorized();

        $executive = User::factory()->create();
        $executive->forceFill(['role' => User::ROLE_EXECUTIVE, 'is_active' => true])->save();
        $this->actingAs($executive);

        $this->postJson(route('documents.direct-url'), ['filename' => 'a.pdf', 'size' => 5_000_000])->assertForbidden();
    }

    public function test_a_file_already_in_storage_is_checked_classified_and_filed(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $officer = $this->officer();
        $this->actingAs($officer);

        Storage::disk('local')->put(self::KEY, $this->pdfBytes());

        $this->withSession(['direct_uploads' => [self::KEY]])
            ->post(route('documents.store'), ['uploaded_key' => self::KEY, 'uploaded_name' => 'Big letter.pdf'])
            ->assertRedirect(route('documents.index'));

        $document = Document::firstOrFail();

        Storage::disk('local')->assertMissing(self::KEY);
        Storage::disk('local')->assertExists($document->stored_path);
        $this->assertStringStartsWith('documents/correspondence/', $document->stored_path);
        $this->assertSame('Big letter.pdf', $document->original_name);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame(hash('sha256', $this->pdfBytes()), $document->file_hash);
        $this->assertSame($officer->id, $document->uploaded_by);
        $this->assertSame('Needs review', $document->review_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.uploaded', 'user_id' => $officer->id]);
    }

    public function test_a_link_that_was_not_issued_to_this_session_is_refused(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->officer());

        Storage::disk('local')->put(self::KEY, $this->pdfBytes());

        $this->post(route('documents.store'), ['uploaded_key' => self::KEY, 'uploaded_name' => 'x.pdf'])
            ->assertSessionHasErrors('document');

        $this->assertSame(0, Document::count());
    }

    public function test_a_link_can_only_be_used_once(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->officer());

        Storage::disk('local')->put(self::KEY, $this->pdfBytes());

        $this->withSession(['direct_uploads' => [self::KEY]])
            ->post(route('documents.store'), ['uploaded_key' => self::KEY, 'uploaded_name' => 'x.pdf'])
            ->assertRedirect(route('documents.index'));

        $this->post(route('documents.store'), ['uploaded_key' => self::KEY, 'uploaded_name' => 'x.pdf'])
            ->assertSessionHasErrors('document');

        $this->assertSame(1, Document::count());
    }

    public function test_a_key_outside_the_incoming_folder_is_refused(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->officer());

        foreach (['documents/correspondence/other.pdf', 'incoming/../documents/x.pdf', 'incoming/not-a-uuid.pdf'] as $key) {
            Storage::disk('local')->put($key, $this->pdfBytes());

            $this->withSession(['direct_uploads' => [$key]])
                ->post(route('documents.store'), ['uploaded_key' => $key, 'uploaded_name' => 'x.pdf'])
                ->assertSessionHasErrors('uploaded_key');
        }

        $this->assertSame(0, Document::count());
    }

    public function test_a_file_whose_content_is_not_what_its_name_says_is_refused_and_removed(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->officer());

        Storage::disk('local')->put(self::KEY, "<?php echo 'not a pdf';");

        $this->withSession(['direct_uploads' => [self::KEY]])
            ->post(route('documents.store'), ['uploaded_key' => self::KEY, 'uploaded_name' => 'x.pdf'])
            ->assertSessionHasErrors('document');

        $this->assertSame(0, Document::count());
        Storage::disk('local')->assertMissing(self::KEY);
    }

    public function test_a_repeat_of_an_already_filed_file_is_blocked_and_the_extra_copy_removed(): void
    {
        Storage::fake('local');
        $this->fakeScanner();
        $this->actingAs($this->officer());

        Storage::disk('local')->put(self::KEY, $this->pdfBytes());
        $this->withSession(['direct_uploads' => [self::KEY]])
            ->post(route('documents.store'), ['uploaded_key' => self::KEY, 'uploaded_name' => 'first.pdf']);

        $second = 'incoming/1c9d3c3e-6f55-4f3a-9a52-2c3f8a6d1e11.pdf';
        Storage::disk('local')->put($second, $this->pdfBytes());

        $this->withSession(['direct_uploads' => [$second]])
            ->post(route('documents.store'), ['uploaded_key' => $second, 'uploaded_name' => 'second.pdf'])
            ->assertSessionHasErrors('document');

        $this->assertSame(1, Document::count());
        Storage::disk('local')->assertMissing($second);
    }

    public function test_a_failed_scan_leaves_nothing_behind_in_storage(): void
    {
        Storage::fake('local');
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('boom', 500)]);
        $this->actingAs($this->officer());

        Storage::disk('local')->put(self::KEY, $this->pdfBytes());

        $this->withSession(['direct_uploads' => [self::KEY]])
            ->post(route('documents.store'), ['uploaded_key' => self::KEY, 'uploaded_name' => 'x.pdf'])
            ->assertSessionHasErrors('document');

        $this->assertSame(0, Document::count());
        Storage::disk('local')->assertMissing(self::KEY);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}

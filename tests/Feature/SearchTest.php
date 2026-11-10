<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = User::ROLE_OFFICER, ?array $sections = null): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'sections' => $sections])->save();

        return $user;
    }

    private function matter(string $reference, string $title, string $section = 'Law'): Matter
    {
        return Matter::forceCreate([
            'matter_reference' => $reference,
            'title' => $title,
            'section' => $section,
            'category' => 'Letter',
            'priority' => 'Normal',
            'status' => 'Open',
            'deadline' => '2026-12-15',
        ]);
    }

    private function document(string $name, string $text, ?Matter $matter = null, ?int $uploader = null): Document
    {
        return Document::create([
            'original_name' => $name,
            'stored_path' => 'documents/letter/'.$name,
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'category' => 'letter',
            'confidence' => 0.8,
            'extracted_text' => $text,
            'review_status' => 'Needs review',
            'matter_id' => $matter?->id,
            'uploaded_by' => $uploader,
        ]);
    }

    public function test_guests_cannot_search(): void
    {
        $this->get(route('search', ['q' => 'anything']))->assertRedirect(route('login'));
    }

    public function test_it_finds_matters_by_reference_and_title(): void
    {
        $this->matter('HM-L-4821', 'Land dispute in Larkana');
        $this->matter('HM-L-5000', 'Police posting');
        $this->actingAs($this->user());

        $this->get(route('search', ['q' => 'hm-l-4821']))
            ->assertOk()
            ->assertSee('HM-L-4821')
            ->assertDontSee('HM-L-5000');

        $this->get(route('search', ['q' => 'larkana']))
            ->assertOk()
            ->assertSee('HM-L-4821');
    }

    public function test_it_finds_words_inside_scanned_letters_and_shows_where(): void
    {
        $this->document('scan-1.pdf', 'The Deputy Commissioner of Sukkur requests a report on the flood relief fund.');
        $this->document('scan-2.pdf', 'Transfer of a clerk.');
        $this->actingAs($this->user());

        $response = $this->get(route('search', ['q' => 'flood relief']));

        $response->assertOk()
            ->assertSee('scan-1.pdf')
            ->assertDontSee('scan-2.pdf')
            ->assertSee('<mark>flood</mark>', false)
            ->assertSee('<mark>relief</mark>', false);
    }

    public function test_every_word_must_match(): void
    {
        $this->document('a.pdf', 'flood in Sukkur');
        $this->document('b.pdf', 'flood in Larkana');
        $this->actingAs($this->user());

        $this->get(route('search', ['q' => 'flood sukkur']))
            ->assertSee('a.pdf')
            ->assertDontSee('b.pdf');
    }

    public function test_it_finds_documents_by_the_name_of_the_file(): void
    {
        $this->document('Letter from Chief Secretary.pdf', 'text');
        $this->actingAs($this->user());

        $this->get(route('search', ['q' => 'chief secretary']))->assertSee('Letter from Chief Secretary.pdf');
    }

    public function test_percent_and_underscore_are_searched_literally(): void
    {
        $this->document('percent.pdf', 'Grant of 50% of the amount.');
        $this->document('plain.pdf', 'Grant of 500 rupees.');
        $this->actingAs($this->user());

        $this->get(route('search', ['q' => '50%']))
            ->assertSee('percent.pdf')
            ->assertDontSee('plain.pdf');
    }

    public function test_scanned_text_cannot_add_markup_to_the_page(): void
    {
        $this->document('evil.pdf', 'Please <script>alert(1)</script> note the report.');
        $this->actingAs($this->user());

        $this->get(route('search', ['q' => 'report']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_the_search_word_is_escaped_in_the_page(): void
    {
        $this->actingAs($this->user());

        $this->get(route('search', ['q' => '<b>x</b>']))
            ->assertOk()
            ->assertDontSee('<b>x</b>', false);
    }

    public function test_an_empty_or_one_letter_search_gives_a_hint_and_no_results(): void
    {
        $this->matter('HM-L-1', 'A');
        $this->actingAs($this->user());

        $this->get(route('search'))->assertOk()->assertDontSee('HM-L-1');
        $this->get(route('search', ['q' => 'a']))->assertOk()->assertDontSee('HM-L-1');
    }

    public function test_an_account_limited_to_a_section_only_finds_that_sections_work(): void
    {
        $law = $this->matter('HM-L-1111', 'Shared keyword matter', 'Law');
        $this->matter('HM-P-2222', 'Shared keyword matter', 'Police');
        $this->document('law-letter.pdf', 'shared keyword text', $law);
        $other = $this->matter('HM-P-3333', 'Another', 'Police');
        $this->document('police-letter.pdf', 'shared keyword text', $other);

        $this->actingAs($this->user(User::ROLE_OFFICER, ['Law']));

        $this->get(route('search', ['q' => 'keyword']))
            ->assertOk()
            ->assertSee('HM-L-1111')
            ->assertSee('law-letter.pdf')
            ->assertDontSee('HM-P-2222')
            ->assertDontSee('police-letter.pdf');
    }

    public function test_executives_can_search_too(): void
    {
        $this->matter('HM-L-7777', 'Visible to all');
        $this->actingAs($this->user(User::ROLE_EXECUTIVE));

        $this->get(route('search', ['q' => 'visible']))->assertOk()->assertSee('HM-L-7777');
    }
}

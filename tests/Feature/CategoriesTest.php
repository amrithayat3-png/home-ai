<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = User::ROLE_OFFICER, ?array $sections = null): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'sections' => $sections])->save();

        return $user;
    }

    private function document(string $name, string $category, string $status = 'Filed', ?Matter $matter = null): Document
    {
        return Document::create([
            'original_name' => $name,
            'stored_path' => 'documents/'.$category.'/'.$name,
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'category' => $category,
            'confidence' => 0.9,
            'review_status' => $status,
            'matter_id' => $matter?->id,
        ]);
    }

    private function card($response, string $key): array
    {
        return $response->viewData('categoryCards')->firstWhere('key', $key);
    }

    public function test_the_categories_tab_shows_every_category_with_its_count(): void
    {
        $this->actingAs($this->user());
        $this->document('a.pdf', 'correspondence');
        $this->document('b.pdf', 'correspondence', 'Needs review');
        $this->document('c.pdf', 'bills');

        $response = $this->get(route('documents.index', ['view' => 'categories']))->assertOk();

        foreach (['Correspondence', 'Bills', 'Invoices', 'Challans', 'Information', 'Miscellaneous', 'Letters'] as $label) {
            $response->assertSee($label);
        }

        $this->assertSame(2, $this->card($response, 'correspondence')['total']);
        $this->assertSame(1, $this->card($response, 'correspondence')['waiting']);
        $this->assertSame(1, $this->card($response, 'bills')['total']);
        $this->assertSame(0, $this->card($response, 'challans')['total']);
        $response->assertSee('1 waiting for review');
    }

    public function test_opening_a_category_lists_only_its_documents(): void
    {
        $this->actingAs($this->user());
        $this->document('letter-one.pdf', 'correspondence');
        $this->document('water-bill.pdf', 'bills');

        $this->get(route('documents.index', ['category' => 'bills']))
            ->assertOk()
            ->assertSee('water-bill.pdf')
            ->assertDontSee('letter-one.pdf')
            ->assertSee('Show all categories');
    }

    public function test_an_unknown_category_is_ignored(): void
    {
        $this->actingAs($this->user());
        $this->document('a.pdf', 'bills');

        $this->get(route('documents.index', ['category' => 'nonsense']))
            ->assertOk()
            ->assertSee('a.pdf');
    }

    public function test_the_category_in_the_list_is_a_link_to_that_category(): void
    {
        $this->actingAs($this->user());
        $this->document('a.pdf', 'challans');

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertSee(route('documents.index', ['category' => 'challans']), false);
    }

    public function test_every_screen_has_a_categories_button_in_the_top_strip(): void
    {
        $this->actingAs($this->user());

        $this->get(route('matters'))
            ->assertOk()
            ->assertSee(route('documents.index', ['view' => 'categories']), false);
    }

    public function test_counts_only_include_documents_the_account_may_see(): void
    {
        $law = Matter::forceCreate(['matter_reference' => 'HM-L-1', 'title' => 'L', 'section' => 'Law', 'category' => 'Letter', 'priority' => 'Normal', 'status' => 'Open', 'deadline' => '2026-12-15']);
        $police = Matter::forceCreate(['matter_reference' => 'HM-P-1', 'title' => 'P', 'section' => 'Police', 'category' => 'Letter', 'priority' => 'Normal', 'status' => 'Open', 'deadline' => '2026-12-15']);
        $this->document('law.pdf', 'bills', 'Filed', $law);
        $this->document('police.pdf', 'bills', 'Filed', $police);

        $this->actingAs($this->user(User::ROLE_OFFICER, ['Law']));
        $response = $this->get(route('documents.index', ['view' => 'categories']))->assertOk();

        $this->assertSame(1, $this->card($response, 'bills')['total']);
    }

    public function test_the_seal_is_big_and_glows_on_every_page(): void
    {
        $this->assertStringContainsString('seal-glow', file_get_contents(public_path('css/home-ui.css')));
        $this->assertStringContainsString('width: 88px', file_get_contents(public_path('css/home-ui.css')));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use App\Reminders\DeadlineStage;
use App\Services\AskHomeAi;
use App\Services\AskQueryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AskQueryEngineTest extends TestCase
{
    use RefreshDatabase;

    private function today(): string
    {
        return DeadlineStage::today(config('reminders.timezone'));
    }

    private function day(int $offset): string
    {
        return date('Y-m-d', strtotime($this->today().' '.($offset >= 0 ? '+' : '').$offset.' days UTC'));
    }

    private function matter(string $reference, string $section, string $status, ?string $deadline, string $priority = 'Normal', array $extra = []): Matter
    {
        return Matter::forceCreate($extra + [
            'matter_reference' => $reference,
            'title' => 'Title '.$reference,
            'section' => $section,
            'category' => 'Letter',
            'priority' => $priority,
            'status' => $status,
            'deadline' => $deadline,
        ]);
    }

    private function seedRecords(): void
    {
        $this->matter('HM-2026-001', 'Police', 'Open', $this->day(-5), 'High');
        $this->matter('HM-2026-002', 'Police', 'Pending', $this->day(3));
        $this->matter('HM-2026-003', 'Prison', 'Open', $this->day(20), 'Low');
        $this->matter('HM-2026-004', 'Prison', 'Closed', $this->day(-30));
        $this->matter('HM-2026-005', 'Police', 'Open', null, 'Critical');
    }

    private function engine(): AskQueryEngine
    {
        return $this->app->make(AskQueryEngine::class);
    }

    public function test_overdue_lists_only_active_matters_past_their_deadline(): void
    {
        $this->seedRecords();

        $result = $this->engine()->run(['intent' => 'list_matters', 'filters' => ['deadline' => 'overdue']]);

        $this->assertStringContainsString('HM-2026-001', $result['answer']);
        $this->assertStringContainsString('OVERDUE by 5 day(s)', $result['answer']);
        $this->assertStringNotContainsString('HM-2026-004', $result['answer'], 'a closed matter is never overdue');
        $this->assertStringNotContainsString('HM-2026-002', $result['answer']);
        $this->assertStringContainsString('1 matter found.', $result['answer']);
        $this->assertSame(['HM-2026-001'], array_column($result['references'], 'reference'));
    }

    public function test_counts_come_from_the_database(): void
    {
        $this->seedRecords();

        $result = $this->engine()->run(['intent' => 'count_matters', 'filters' => ['section' => 'police']]);

        $this->assertStringContainsString('Total: 3 matters.', $result['answer']);

        $any = $this->engine()->run(['intent' => 'count_matters', 'filters' => ['status' => 'Any']]);

        $this->assertStringContainsString('Total: 5 matters.', $any['answer']);
    }

    public function test_group_by_section_adds_up(): void
    {
        $this->seedRecords();

        $result = $this->engine()->run(['intent' => 'count_matters', 'group_by' => 'section']);

        $this->assertStringContainsString('Total: 4 matters.', $result['answer']);
        $this->assertStringContainsString('- Police: 3', $result['answer']);
        $this->assertStringContainsString('- Prison: 1', $result['answer']);
    }

    public function test_urgent_means_overdue_or_due_within_a_week_for_active_matters(): void
    {
        $this->seedRecords();

        $result = $this->engine()->run(['intent' => 'list_matters', 'filters' => ['deadline' => 'overdue_or_next_7_days']]);

        $references = array_column($result['references'], 'reference');
        sort($references);

        $this->assertSame(['HM-2026-001', 'HM-2026-002'], $references);
    }

    public function test_a_matter_is_shown_with_its_recorded_fields_only(): void
    {
        $this->matter('HM-2026-010', 'Arms', 'Open', $this->day(2), 'High', ['summary' => 'Licence review.', 'assigned_officer' => 'Officer One']);

        $result = $this->engine()->run(['intent' => 'get_matter', 'filters' => ['reference' => 'hm 2026 010']]);

        $this->assertStringContainsString('HM-2026-010: Title HM-2026-010', $result['answer']);
        $this->assertStringContainsString('Summary: Licence review.', $result['answer']);
        $this->assertStringContainsString('Assigned officer: Officer One', $result['answer']);
        $this->assertStringContainsString('District: not recorded', $result['answer']);
        $this->assertStringContainsString('due in 2 day(s)', $result['answer']);
    }

    public function test_an_unknown_matter_is_reported_not_invented(): void
    {
        $result = $this->engine()->run(['intent' => 'get_matter', 'filters' => ['reference' => 'HM-2026-999']]);

        $this->assertStringContainsString('No matter with the reference HM-2026-999', $result['answer']);
        $this->assertSame([], $result['references']);
    }

    public function test_an_unrecognised_value_is_refused_not_guessed(): void
    {
        $this->seedRecords();

        $section = $this->engine()->run(['intent' => 'list_matters', 'filters' => ['section' => 'Wizardry']]);
        $this->assertStringContainsString('I did not recognise the section "Wizardry"', $section['answer']);

        $status = $this->engine()->run(['intent' => 'list_matters', 'filters' => ['status' => 'Halfway']]);
        $this->assertStringContainsString('I did not recognise the status "Halfway"', $status['answer']);
    }

    public function test_an_unknown_intent_gets_the_cannot_answer_message(): void
    {
        $result = $this->engine()->run(['intent' => 'unknown']);

        $this->assertStringContainsString("I can't answer that from the records.", $result['answer']);
        $this->assertSame([], $result['references']);
    }

    public function test_search_text_treats_percent_signs_literally(): void
    {
        $this->matter('HM-2026-020', 'Police', 'Open', $this->day(1), 'Normal', ['title' => 'Budget 50% review']);
        $this->matter('HM-2026-021', 'Police', 'Open', $this->day(1), 'Normal', ['title' => 'Budget 500 review']);

        $result = $this->engine()->run(['intent' => 'list_matters', 'filters' => ['search' => '50%']]);

        $this->assertSame(['HM-2026-020'], array_column($result['references'], 'reference'));
    }

    public function test_briefing_reports_counts_from_the_database(): void
    {
        $this->seedRecords();

        $result = $this->engine()->run(['intent' => 'briefing']);

        $this->assertStringContainsString('Overdue: 1', $result['answer']);
        $this->assertStringContainsString('Due within 7 days (including today): 1', $result['answer']);
        $this->assertStringContainsString('High or Critical priority: 2', $result['answer']);
    }

    public function test_documents_can_be_listed_and_read_back(): void
    {
        $document = Document::create([
            'original_name' => 'bill.pdf',
            'stored_path' => 'documents/bills/x.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'category' => 'bills',
            'review_status' => 'Needs review',
            'extracted_text' => 'Electricity bill for September.',
        ]);

        $list = $this->engine()->run(['intent' => 'list_documents', 'documents' => ['category' => 'bills', 'review_status' => 'Needs review']]);
        $this->assertStringContainsString('DOC-'.$document->id.' | bill.pdf', $list['answer']);
        $this->assertSame('DOC-'.$document->id, $list['documents'][0]['id']);

        $text = $this->engine()->run(['intent' => 'document_text', 'document_id' => $document->id]);
        $this->assertStringContainsString('Electricity bill for September.', $text['answer']);
        $this->assertStringContainsString('written by the AI scanner', $text['answer']);
    }

    public function test_account_section_limits_apply_to_answers(): void
    {
        $this->seedRecords();

        $officer = User::factory()->create();
        $officer->forceFill(['role' => User::ROLE_OFFICER, 'sections' => ['Prison']])->save();
        $this->actingAs($officer);

        $result = $this->engine()->run(['intent' => 'count_matters', 'filters' => ['status' => 'Any']]);

        $this->assertStringContainsString('Total: 2 matters.', $result['answer']);

        $hidden = $this->engine()->run(['intent' => 'get_matter', 'filters' => ['reference' => 'HM-2026-001']]);
        $this->assertStringContainsString('No matter with the reference HM-2026-001', $hidden['answer']);
    }

    public function test_the_plan_parser_never_trusts_the_model(): void
    {
        $ai = $this->app->make(AskHomeAi::class);

        $this->assertSame('unknown', $ai->parsePlan('not json at all')['intent']);
        $this->assertSame('unknown', $ai->parsePlan('{"intent": "drop_table"}')['intent']);
        $this->assertSame('unknown', $ai->parsePlan('[1,2,3]')['intent']);

        $plan = $ai->parsePlan("```json\n{\"intent\": \"count_matters\", \"filters\": {\"section\": \"Police\"}, \"group_by\": \"status\"}\n```");

        $this->assertSame('count_matters', $plan['intent']);
        $this->assertSame('Police', $plan['filters']['section']);
        $this->assertSame('status', $plan['group_by']);
    }
}

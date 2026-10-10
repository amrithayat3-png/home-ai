<?php

namespace Tests\Feature;

use App\Models\Matter;
use App\Models\Reminder;
use App\Models\User;
use App\Reminders\DeadlineStage;
use App\Reminders\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, bool $active = true): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => $active])->save();

        return $user;
    }

    private function matter(string $reference, string $deadline, string $priority = 'Normal', string $status = 'Open'): Matter
    {
        return Matter::forceCreate([
            'matter_reference' => $reference,
            'title' => 'Test matter '.$reference,
            'section' => 'Law',
            'category' => 'Letter',
            'priority' => $priority,
            'status' => $status,
            'deadline' => $deadline,
        ]);
    }

    public function test_stage_and_level_follow_days_left(): void
    {
        $this->assertSame('overdue', DeadlineStage::stageFor(-1));
        $this->assertSame('due', DeadlineStage::stageFor(0));
        $this->assertSame('d1', DeadlineStage::stageFor(1));
        $this->assertSame('d3', DeadlineStage::stageFor(3));
        $this->assertSame('d7', DeadlineStage::stageFor(7));
        $this->assertNull(DeadlineStage::stageFor(8));
        $this->assertSame(3, DeadlineStage::daysLeft('2026-10-13', '2026-10-10'));
        $this->assertSame(-2, DeadlineStage::daysLeft('2026-10-08', '2026-10-10'));
    }

    public function test_run_creates_reminders_for_admins_and_officers_only(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $officer = $this->userWithRole(User::ROLE_OFFICER);
        $executive = $this->userWithRole(User::ROLE_EXECUTIVE);
        $inactive = $this->userWithRole(User::ROLE_OFFICER, false);

        $this->matter('HM-T-001', '2026-10-13');

        $result = app(ReminderService::class)->run('2026-10-10');

        $this->assertSame(2, $result['sent']);
        $this->assertDatabaseHas('reminders', ['user_id' => $admin->id, 'stage' => 'd3']);
        $this->assertDatabaseHas('reminders', ['user_id' => $officer->id, 'stage' => 'd3']);
        $this->assertDatabaseMissing('reminders', ['user_id' => $executive->id]);
        $this->assertDatabaseMissing('reminders', ['user_id' => $inactive->id]);
    }

    public function test_run_twice_does_not_duplicate(): void
    {
        $this->userWithRole(User::ROLE_ADMIN);
        $this->matter('HM-T-002', '2026-10-10');

        app(ReminderService::class)->run('2026-10-10');
        $second = app(ReminderService::class)->run('2026-10-10');

        $this->assertSame(0, $second['sent']);
        $this->assertSame(1, Reminder::count());
    }

    public function test_closed_and_far_matters_get_no_reminder(): void
    {
        $this->userWithRole(User::ROLE_ADMIN);
        $this->matter('HM-T-003', '2026-10-11', 'Normal', 'Closed');
        $this->matter('HM-T-004', '2026-12-01');

        app(ReminderService::class)->run('2026-10-10');

        $this->assertSame(0, Reminder::count());
    }

    public function test_changed_deadline_sends_a_fresh_reminder(): void
    {
        $this->userWithRole(User::ROLE_ADMIN);
        $matter = $this->matter('HM-T-005', '2026-10-11');

        app(ReminderService::class)->run('2026-10-10');
        $matter->forceFill(['deadline' => '2026-10-12'])->save();
        app(ReminderService::class)->run('2026-10-10');

        $this->assertSame(2, Reminder::count());
    }

    public function test_dashboard_shows_deadline_watch_sorted_by_priority(): void
    {
        $this->matter('HM-T-010', DeadlineStage::today(config('reminders.timezone')), 'Normal');
        $this->matter('HM-T-011', DeadlineStage::today(config('reminders.timezone')), 'High');

        $this->actingAs($this->userWithRole(User::ROLE_EXECUTIVE));

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['HM-T-011', 'HM-T-010'])
            ->assertSee('Due today');
    }

    public function test_user_can_mark_only_their_own_reminder_read(): void
    {
        $owner = $this->userWithRole(User::ROLE_OFFICER);
        $other = $this->userWithRole(User::ROLE_OFFICER);
        $matter = $this->matter('HM-T-006', '2026-10-10');

        $reminder = Reminder::create([
            'matter_id' => $matter->id,
            'user_id' => $owner->id,
            'stage' => 'due',
            'deadline' => '2026-10-10',
            'message' => 'x',
        ]);

        $this->actingAs($other)->patch(route('reminders.read', $reminder))->assertForbidden();
        $this->actingAs($owner)->patch(route('reminders.read', $reminder))->assertRedirect();

        $this->assertNotNull($reminder->fresh()->read_at);
    }

    public function test_external_channels_stay_off_by_default(): void
    {
        $this->assertFalse(config('reminders.external_enabled'));
        $this->assertSame([], config('reminders.external_channels'));
    }
}

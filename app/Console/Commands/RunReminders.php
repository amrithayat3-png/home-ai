<?php

namespace App\Console\Commands;

use App\Reminders\ReminderService;
use Illuminate\Console\Command;

class RunReminders extends Command
{
    protected $signature = 'reminders:run {--date= : Pretend today is this date (Y-m-d), for testing}';

    protected $description = 'Create deadline reminders that are due today (7 days, 3 days, 1 day, due today, overdue)';

    public function handle(ReminderService $service): int
    {
        $date = $this->option('date');

        if ($date !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            $this->error('Use the date format Y-m-d, for example 2026-10-12.');

            return self::FAILURE;
        }

        $result = $service->run($date ?: null);

        $this->info("Matters at a reminder stage: {$result['matters']}. New reminders: {$result['sent']}. Already sent: {$result['skipped']}.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Matter;
use Illuminate\Console\Command;

class RedateDemoMatters extends Command
{
    protected $signature = 'demo:redate-matters';

    protected $description = 'Reset the deadlines of the 20 synthetic demo matters so they are relative to today';

    /**
     * Days from today for each demo matter. Negative values are overdue.
     */
    private const OFFSETS = [
        'HM-2026-001' => -2,
        'HM-2026-002' => 1,
        'HM-2026-003' => 3,
        'HM-2026-004' => 2,
        'HM-2026-005' => 6,
        'HM-2026-006' => 9,
        'HM-2026-007' => 0,
        'HM-2026-008' => 5,
        'HM-2026-009' => 4,
        'HM-2026-010' => 12,
        'HM-2026-011' => 7,
        'HM-2026-012' => 2,
        'HM-2026-014' => 15,
        'HM-2026-015' => 10,
        'HM-2026-016' => 20,
        'HM-2026-017' => 1,
        'HM-2026-018' => 25,
        'HM-2026-019' => 8,
        'HM-2026-020' => 3,
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This command only changes synthetic demo data and will not run in production.');

            return self::FAILURE;
        }

        $updated = 0;

        foreach (self::OFFSETS as $reference => $days) {
            $matter = Matter::where('matter_reference', $reference)->first();

            if (! $matter) {
                continue;
            }

            $matter->deadline = now()->addDays($days)->toDateString();
            $matter->save();
            $updated++;
        }

        $this->info("Updated the deadlines of {$updated} demo matters, relative to today.");

        return self::SUCCESS;
    }
}

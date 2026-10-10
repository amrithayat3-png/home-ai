<?php

namespace App\Reminders;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Pure deadline maths with no framework dependency, so it is easy to test.
 * All dates are plain "Y-m-d" strings.
 */
final class DeadlineStage
{
    public const D7 = 'd7';

    public const D3 = 'd3';

    public const D1 = 'd1';

    public const DUE = 'due';

    public const OVERDUE = 'overdue';

    public static function today(string $timezone = 'Asia/Karachi'): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d');
    }

    /**
     * Whole days from $today to $deadline. Negative when the deadline has passed.
     */
    public static function daysLeft(string $deadline, string $today): int
    {
        $d = strtotime(substr($deadline, 0, 10).' 00:00:00 UTC');
        $t = strtotime(substr($today, 0, 10).' 00:00:00 UTC');

        return (int) round(($d - $t) / 86400);
    }

    /**
     * The most urgent stage a matter has reached, or null when it is more than 7 days away.
     * If the scheduler missed a day, the current stage is still returned.
     */
    public static function stageFor(int $daysLeft): ?string
    {
        return match (true) {
            $daysLeft < 0 => self::OVERDUE,
            $daysLeft === 0 => self::DUE,
            $daysLeft <= 1 => self::D1,
            $daysLeft <= 3 => self::D3,
            $daysLeft <= 7 => self::D7,
            default => null,
        };
    }

    /**
     * Colour level for badges: overdue, today, soon (1 to 3 days), week (4 to 7), later.
     */
    public static function level(int $daysLeft): string
    {
        return match (true) {
            $daysLeft < 0 => 'overdue',
            $daysLeft === 0 => 'today',
            $daysLeft <= 3 => 'soon',
            $daysLeft <= 7 => 'week',
            default => 'later',
        };
    }

    public static function label(int $daysLeft): string
    {
        return match (true) {
            $daysLeft < 0 => 'Overdue by '.abs($daysLeft).' '.(abs($daysLeft) === 1 ? 'day' : 'days'),
            $daysLeft === 0 => 'Due today',
            $daysLeft === 1 => 'Due tomorrow',
            default => "Due in {$daysLeft} days",
        };
    }

    /**
     * Sort rank: lower is more urgent.
     */
    public static function priorityRank(?string $priority): int
    {
        return match ($priority) {
            'Critical' => 0,
            'High' => 1,
            'Normal' => 2,
            'Low' => 3,
            default => 4,
        };
    }
}

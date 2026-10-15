<?php

namespace App\Models;

use App\Reminders\DeadlineStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matter extends Model
{
    /**
     * Accounts limited to some sections only see matters in those sections.
     * Administrators, unrestricted accounts and command-line jobs see everything.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('sections', function (Builder $builder) {
            $sections = static::restrictedSections();

            if ($sections !== null) {
                $builder->whereIn($builder->getModel()->getTable().'.section', $sections);
            }
        });
    }

    /**
     * The sections the signed-in account is limited to, or null when it may see all of them.
     *
     * @return array<int, string>|null
     */
    public static function restrictedSections(): ?array
    {
        $user = auth()->user();

        return $user instanceof User ? $user->sectionLimit() : null;
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'matter_id')->latest();
    }

    /**
     * Whole days until the deadline (negative when overdue), or null when there is no deadline.
     */
    public function daysLeft(): ?int
    {
        if (! $this->deadline) {
            return null;
        }

        return DeadlineStage::daysLeft(
            (string) $this->deadline,
            DeadlineStage::today(config('reminders.timezone')),
        );
    }

    /**
     * Badge for lists: ['level' => overdue|today|soon|week|later, 'label' => 'Due in 3 days'].
     * Closed matters and matters without a deadline get no badge.
     *
     * @return array{level: string, label: string}|null
     */
    public function deadlineBadge(): ?array
    {
        $days = $this->daysLeft();

        if ($days === null || ! in_array($this->status, ['Open', 'Pending'], true)) {
            return null;
        }

        return [
            'level' => DeadlineStage::level($days),
            'label' => DeadlineStage::label($days),
        ];
    }
}

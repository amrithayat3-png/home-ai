<?php

namespace App\Reminders;

interface ReminderChannel
{
    /**
     * Short channel name, for example "in_app", "email", "whatsapp".
     */
    public function name(): string;

    /**
     * Deliver one reminder.
     * Return true when something new was sent, false when it was skipped (for example a duplicate).
     */
    public function deliver(ReminderMessage $message): bool;
}

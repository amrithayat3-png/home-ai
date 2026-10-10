<?php

namespace App\Reminders\Channels;

use App\Models\Reminder;
use App\Reminders\ReminderChannel;
use App\Reminders\ReminderMessage;

class InAppChannel implements ReminderChannel
{
    public function name(): string
    {
        return 'in_app';
    }

    public function deliver(ReminderMessage $message): bool
    {
        if ($message->recipient === null) {
            return false;
        }

        $reminder = Reminder::firstOrCreate(
            [
                'matter_id' => $message->matterId,
                'user_id' => $message->recipient->id,
                'stage' => $message->stage,
                'deadline' => $message->deadline,
            ],
            [
                'message' => $message->text,
            ],
        );

        return $reminder->wasRecentlyCreated;
    }
}

<?php

namespace App\Reminders;

use App\Models\Matter;
use App\Models\User;

class ReminderService
{
    /**
     * Create the reminders that are due today. Safe to run many times a day:
     * each matter, person and deadline gets each stage only once.
     *
     * @return array{matters: int, sent: int, skipped: int}
     */
    public function run(?string $today = null): array
    {
        $today ??= DeadlineStage::today(config('reminders.timezone'));

        $matters = Matter::whereIn('status', ['Open', 'Pending'])
            ->whereNotNull('deadline')
            ->get();

        $internalUsers = User::where('is_active', true)
            ->whereIn('role', config('reminders.internal_roles', []))
            ->get();

        $sent = 0;
        $skipped = 0;
        $touched = 0;

        foreach ($matters as $matter) {
            $days = DeadlineStage::daysLeft($matter->deadline, $today);
            $stage = DeadlineStage::stageFor($days);

            if ($stage === null) {
                continue;
            }

            $touched++;

            foreach ($this->messagesFor($matter, $stage, $days, $internalUsers) as [$audience, $message]) {
                foreach ($this->channels($audience) as $channel) {
                    $channel->deliver($message) ? $sent++ : $skipped++;
                }
            }
        }

        return ['matters' => $touched, 'sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * @return iterable<array{0: string, 1: ReminderMessage}>
     */
    private function messagesFor(Matter $matter, string $stage, int $days, $internalUsers): iterable
    {
        $deadline = substr((string) $matter->deadline, 0, 10);
        $when = strtolower(DeadlineStage::label($days));
        $safe = "{$matter->matter_reference} {$when} (deadline {$deadline}). Open HOME AI for details.";
        $full = "{$matter->matter_reference} {$when} (deadline {$deadline}): {$matter->title}";

        foreach ($internalUsers as $user) {
            yield [ReminderMessage::AUDIENCE_INTERNAL, new ReminderMessage(
                audience: ReminderMessage::AUDIENCE_INTERNAL,
                matterId: $matter->id,
                matterReference: $matter->matter_reference,
                deadline: $deadline,
                stage: $stage,
                text: $full,
                safeText: $safe,
                recipient: $user,
            )];
        }

        if (config('reminders.external_enabled')) {
            foreach ($this->externalRecipients($matter) as $contact) {
                yield [ReminderMessage::AUDIENCE_EXTERNAL, new ReminderMessage(
                    audience: ReminderMessage::AUDIENCE_EXTERNAL,
                    matterId: $matter->id,
                    matterReference: $matter->matter_reference,
                    deadline: $deadline,
                    stage: $stage,
                    text: $full,
                    safeText: $safe,
                    contact: $contact,
                )];
            }
        }
    }

    /**
     * LATER: return the outside people to remind about this matter, each as
     * ['name' => ..., 'email' => ..., 'phone' => ...]. Empty for now.
     *
     * @return array<int, array<string, string>>
     */
    protected function externalRecipients(Matter $matter): array
    {
        return [];
    }

    /**
     * @return array<int, ReminderChannel>
     */
    private function channels(string $audience): array
    {
        $key = $audience === ReminderMessage::AUDIENCE_EXTERNAL ? 'external_channels' : 'internal_channels';

        return collect(config("reminders.{$key}", []))
            ->map(fn (string $class) => app($class))
            ->values()
            ->all();
    }
}

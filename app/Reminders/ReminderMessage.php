<?php

namespace App\Reminders;

use App\Models\User;

/**
 * One reminder, ready to be delivered by any channel.
 *
 * "text" has the matter title and is for signed-in users.
 * "safeText" has only the reference and the date. Use it for anything that
 * leaves HOME AI (email, WhatsApp) so no case detail goes to outside servers.
 */
final class ReminderMessage
{
    public const AUDIENCE_INTERNAL = 'internal';

    public const AUDIENCE_EXTERNAL = 'external';

    public function __construct(
        public readonly string $audience,
        public readonly int $matterId,
        public readonly string $matterReference,
        public readonly string $deadline,
        public readonly string $stage,
        public readonly string $text,
        public readonly string $safeText,
        public readonly ?User $recipient = null,
        /** For external audiences later: ['name' => ..., 'email' => ..., 'phone' => ...] */
        public readonly array $contact = [],
    ) {}
}

<?php

use App\Reminders\Channels\InAppChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Reminder timezone
    |--------------------------------------------------------------------------
    | Deadlines are plain dates, so "today" must follow the department's own
    | clock, not the server's UTC clock.
    */
    'timezone' => env('REMINDER_TIMEZONE', 'Asia/Karachi'),

    /*
    |--------------------------------------------------------------------------
    | Stages
    |--------------------------------------------------------------------------
    | A reminder fires once per matter, recipient and deadline when the matter
    | reaches a stage: 7 days, 3 days, 1 day, due today, overdue.
    | The days are fixed in App\Reminders\DeadlineStage.
    */

    /*
    |--------------------------------------------------------------------------
    | Internal audience (people who sign in to HOME AI)
    |--------------------------------------------------------------------------
    | Roles that receive reminders. Executives can be added later.
    */
    'internal_roles' => ['admin', 'officer'],

    /*
    |--------------------------------------------------------------------------
    | Channels per audience
    |--------------------------------------------------------------------------
    | name => class implementing App\Reminders\ReminderChannel.
    |
    | LATER: to add email or WhatsApp for external people, write a class that
    | implements ReminderChannel, list it under "external" below, set
    | 'external_enabled' to true, and fill ReminderService::externalRecipients().
    | Nothing else needs to change.
    */
    'internal_channels' => [
        'in_app' => InAppChannel::class,
    ],

    'external_enabled' => false,

    'external_channels' => [
        // 'email' => \App\Reminders\Channels\EmailChannel::class,
        // 'whatsapp' => \App\Reminders\Channels\WhatsAppChannel::class,
    ],

];

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deadline reminders: every morning, Karachi time. Needs the scheduler running (php artisan schedule:work locally).
Schedule::command('reminders:run')
    ->dailyAt('07:00')
    ->timezone(config('reminders.timezone', 'Asia/Karachi'));

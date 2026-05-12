<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled Tasks ─────────────────────────────────────────
// Run: php artisan schedule:run (every minute via cron)
// Or manually: php artisan bookings:expire-pending

Schedule::command('bookings:expire-pending --minutes=15')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/expire-pending.log'));

Schedule::command('bookings:send-reminders --minutes=120')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/booking-reminders.log'));

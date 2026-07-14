<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires a real cron entry running `php artisan schedule:run` every minute
// in production (see the deployment guide) - nothing fires this on its own.
// Runs every 15 minutes (not hourly) so the ~1-hour-before window the
// command checks is actually caught close to on time rather than up to an
// hour late; withoutOverlapping guards against a slow run still executing
// when the next tick fires.
Schedule::command('events:send-reminders')->everyFifteenMinutes()->withoutOverlapping();

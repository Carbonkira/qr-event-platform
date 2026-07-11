<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires a real cron entry running `php artisan schedule:run` every minute
// in production (see the deployment guide) - nothing fires this on its own.
Schedule::command('events:send-reminders')->hourly();
Schedule::command('events:mark-completed')->hourly();

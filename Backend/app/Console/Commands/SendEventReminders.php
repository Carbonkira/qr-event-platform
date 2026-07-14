<?php

namespace App\Console\Commands;

use App\Mail\EventReminderMail;
use App\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Email each registrant a one-time reminder about an hour before their event starts';

    public function handle(): int
    {
        // date and start_time are separate columns (a plain DATE plus an
        // "HH:MM" string - see the events migration), so there's no portable
        // way to compare a single "starts at" timestamp directly in SQL
        // across both Postgres (production) and SQLite (tests). Narrow to
        // the event's date first (cheap, indexed), then build the real
        // start datetime in PHP and check it against the window below.
        $now = now();
        $windowStart = $now->copy()->addMinutes(45);
        $windowEnd = $now->copy()->addMinutes(75);

        $sent = 0;

        Registration::with('event')
            ->whereNull('reminder_sent_at')
            ->whereHas('event', function ($query) use ($windowStart, $windowEnd) {
                // whereDate() (not whereBetween on the raw column) so this
                // still works if "date" is stored with a time-of-day
                // component attached, not a bare Y-m-d value.
                $query->where('status', 'approved')
                    ->whereDate('date', '>=', $windowStart->toDateString())
                    ->whereDate('date', '<=', $windowEnd->toDateString());
            })
            ->chunkById(200, function ($registrations) use ($windowStart, $windowEnd, &$sent) {
                foreach ($registrations as $registration) {
                    $startsAt = $registration->event->date->copy()->setTimeFromTimeString($registration->event->start_time ?? '00:00');

                    if (! $startsAt->between($windowStart, $windowEnd)) {
                        continue;
                    }

                    try {
                        Mail::to($registration->email)->queue(new EventReminderMail($registration));
                        // Marked immediately (not after the loop) so this
                        // registration can never match again on a later run,
                        // even if that run starts before this email finishes
                        // queueing - this is the actual "send once" guarantee.
                        $registration->update(['reminder_sent_at' => now()]);
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::error('Failed to queue event reminder email', ['registration_id' => $registration->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}

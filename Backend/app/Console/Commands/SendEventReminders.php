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

    protected $description = 'Email a reminder to registrants whose event starts within the next day';

    public function handle(): int
    {
        $registrations = Registration::with('event')
            ->whereNull('reminder_sent_at')
            ->whereHas('event', function ($query) {
                $query->whereBetween('date', [now()->toDateString(), now()->addDay()->toDateString()])
                    ->where('status', 'approved');
            })
            ->get();

        foreach ($registrations as $registration) {
            try {
                Mail::to($registration->email)->queue(new EventReminderMail($registration));
                $registration->update(['reminder_sent_at' => now()]);
            } catch (\Throwable $e) {
                Log::error('Failed to queue event reminder email', ['registration_id' => $registration->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Sent {$registrations->count()} reminder(s).");

        return self::SUCCESS;
    }
}

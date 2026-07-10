<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    public function build()
    {
        $event = $this->registration->event;

        return $this->subject("Reminder: {$event->title} is coming up")
            ->view('emails.event-reminder', [
                'registration' => $this->registration,
                'event' => $event,
                'qrUrl' => url("/api/registrations/{$this->registration->id}/qr.png"),
            ]);
    }
}

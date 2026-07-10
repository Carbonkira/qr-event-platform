<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    public function build()
    {
        $event = $this->registration->event;
        $subject = $this->registration->waitlisted
            ? "You're on the waitlist: {$event->title}"
            : "You're registered: {$event->title}";

        return $this->subject($subject)
            ->view('emails.registration-confirmed', [
                'registration' => $this->registration,
                'event' => $event,
                'qrUrl' => url("/api/registrations/{$this->registration->id}/qr.png"),
            ]);
    }
}

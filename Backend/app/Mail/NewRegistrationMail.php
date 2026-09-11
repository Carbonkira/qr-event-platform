<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to an event's organizer the moment a participant finishes
 * self-service registration (RegistrationController::store()) - not for
 * walkIn()/importCsv(), which the organizer already triggered themselves.
 * Previously the organizer had no way to find out someone registered short
 * of checking the event's Guests list themselves.
 */
class NewRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    public function build()
    {
        $event = $this->registration->event;
        $frontend = rtrim(config('services.frontend.url'), '/');

        return $this->subject("New registration: {$event->title}")
            ->view('emails.new-registration', [
                'registration' => $this->registration,
                'event' => $event,
                'eventUrl' => "{$frontend}/organizer/events/{$event->id}",
            ]);
    }
}

<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration, public Event $event)
    {
    }

    public function build()
    {
        return $this->subject("Cancelled: {$this->event->title}")
            ->view('emails.event-cancelled', [
                'registration' => $this->registration,
                'event' => $this->event,
            ]);
    }
}

<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    public function build()
    {
        $event = $this->registration->event;

        return $this->subject("Payment couldn't be verified: {$event->title}")
            ->view('emails.payment-rejected', [
                'registration' => $this->registration,
                'event' => $event,
            ]);
    }
}

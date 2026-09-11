<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentVerifiedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    public function build()
    {
        $event = $this->registration->event;

        return $this->subject("Payment verified: {$event->title}")
            ->view('emails.payment-verified', [
                'registration' => $this->registration,
                'event' => $event,
                'qrUrl' => url("/api/registrations/{$this->registration->id}/qr.png?token={$this->registration->pass_token}"),
            ]);
    }
}

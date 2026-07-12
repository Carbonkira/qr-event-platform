<?php

namespace App\Mail;

use App\Models\Connection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConnectionRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    // Named $connectionRecord, not $connection - Queueable already declares
    // a $connection property (the queue connection name) and a same-named
    // but differently-typed property collides fatally at class composition.
    public function __construct(public Connection $connectionRecord)
    {
    }

    public function build()
    {
        $requester = $this->connectionRecord->requester;
        $frontend = rtrim(config('services.frontend.url'), '/');

        return $this->subject("{$requester->name} wants to connect on QRMeets")
            ->view('emails.connection-request', [
                'requester' => $requester,
                'connectionsUrl' => "{$frontend}/connections",
            ]);
    }
}

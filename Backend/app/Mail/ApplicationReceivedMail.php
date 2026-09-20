<?php

namespace App\Mail;

use App\Services\AdminContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "We have received your application for your {organizer account | event}.
 * You can contact us here {admin number}" - sent to the applicant the
 * moment they submit either kind of application, so they know it landed and
 * who to reach while an admin reviews it. One class for both because the
 * only thing that differs is what they applied for and the summary rows.
 *
 * @param  string  $applicationFor  Read inside the sentence, e.g. "organizer account" or "event \"Tech Meetup\"".
 * @param  array<string,string>  $details  Label => value rows shown under the message (empty values are skipped).
 */
class ApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $applicationFor,
        public array $details = [],
    ) {
    }

    public function build()
    {
        // 'rows', not 'details': the public $details property is auto-shared
        // with the view and would override a same-named key, un-doing the
        // blank-value filtering (an empty "Organization:" line would show).
        $mail = $this->subject("We received your application for your {$this->applicationFor}")
            ->view('emails.application-received', [
                'rows' => array_filter($this->details, fn ($v) => filled($v)),
                'adminNumber' => AdminContact::number(),
            ]);

        // Hitting reply reaches the admin, not the sending-only From address.
        if ($adminEmail = AdminContact::email()) {
            $mail->replyTo($adminEmail, config('mail.from.name'));
        }

        return $mail;
    }
}

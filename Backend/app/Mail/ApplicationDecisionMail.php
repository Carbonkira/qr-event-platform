<?php

namespace App\Mail;

use App\Services\AdminContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The other half of ApplicationReceivedMail: once the admin has decided, the
 * applicant hears about it instead of having to keep logging in to find out.
 * One class for both kinds of application (an organizer account, or an
 * event) and both outcomes, since only the wording and the next step differ.
 *
 * @param  string  $applicationFor  Read inside the sentence, e.g. "organizer account" or "event \"Tech Meetup\"".
 * @param  array<string,string>  $details  Label => value rows (which organization they were added to, the event's date...); empty values are skipped.
 * @param  string|null  $actionUrl  Where "what now?" points on approval - login for an account, the live page for an event.
 * @param  string|null  $reason  The admin's own words for why, on a rejection only - shown to the applicant as written.
 */
class ApplicationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $applicationFor,
        public bool $approved,
        public array $details = [],
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public ?string $reason = null,
    ) {
    }

    public function build()
    {
        $subject = $this->approved
            ? "Your {$this->applicationFor} was approved"
            : "Update on your {$this->applicationFor}";

        // The view keys below (rows/ctaUrl/ctaLabel) are deliberately NOT the
        // same as this class's public properties (details/actionUrl/
        // actionLabel): Laravel auto-shares every public property with the
        // view and lets it override view data of the same name, which would
        // silently undo the filtering and the "no button on a rejection" rule.
        $mail = $this->subject($subject)
            ->view('emails.application-decision', [
                'rows' => array_filter($this->details, fn ($v) => filled($v)),
                'ctaUrl' => $this->approved ? $this->actionUrl : null,
                'ctaLabel' => $this->actionLabel,
                'reasonText' => (! $this->approved && filled($this->reason)) ? trim($this->reason) : null,
                // Only surfaced on a rejection - that's when someone has a
                // question and needs a person to ask.
                'adminNumber' => $this->approved ? null : AdminContact::number(),
            ]);

        // Unlike the number above, this applies to both outcomes: an approved
        // applicant replying with a question should reach the admin too, not
        // the sending-only From address.
        if ($adminEmail = AdminContact::email()) {
            $mail->replyTo($adminEmail, config('mail.from.name'));
        }

        return $mail;
    }
}

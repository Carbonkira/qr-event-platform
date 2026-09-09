<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to every admin the moment an event reaches 'pending' (store() with
 * no draft, or submit() moving a draft into the queue) - previously an
 * org-backed event auto-approved with no admin involved at all; now every
 * event needs the admin's explicit approve/reject regardless of who
 * submitted it, so the admin needs to actually find out it happened rather
 * than having to remember to check the Approvals page.
 */
class EventSubmittedForApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event, public ?Organizer $submitter)
    {
    }

    public function build()
    {
        $frontend = rtrim(config('services.frontend.url'), '/');

        return $this->subject("Event awaiting approval: {$this->event->title}")
            ->view('emails.event-submitted-for-approval', [
                'event' => $this->event,
                'submitter' => $this->submitter,
                'approvalsUrl' => "{$frontend}/organizer/approvals",
            ]);
    }
}

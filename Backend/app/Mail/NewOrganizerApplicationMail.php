<?php

namespace App\Mail;

use App\Models\Organizer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to every admin when someone applies for an organizer account - the
 * counterpart of EventSubmittedForApprovalMail. Until now an admin only
 * found out by remembering to open the Approvals page.
 */
class NewOrganizerApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Organizer $applicant)
    {
    }

    public function build()
    {
        $frontend = rtrim(config('services.frontend.url'), '/');

        return $this->subject("Organizer application awaiting approval: {$this->applicant->name}")
            ->view('emails.new-organizer-application', [
                'applicant' => $this->applicant,
                'requestedOrganization' => $this->applicant->requestedOrganization?->name ?? $this->applicant->requested_organization_name,
                'approvalsUrl' => "{$frontend}/organizer/approvals?tab=organizers",
            ]);
    }
}

<?php

namespace App\Mail;

use App\Models\OrganizationInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrganizationInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrganizationInvite $invite)
    {
    }

    public function build()
    {
        $organization = $this->invite->organization;
        $frontend = rtrim(config('services.frontend.url'), '/');

        return $this->subject("You're invited to join {$organization->name} on QRMeets")
            ->view('emails.organization-invite', [
                'invite' => $this->invite,
                'organization' => $organization,
                'inviter' => $this->invite->inviter,
                'acceptUrl' => "{$frontend}/invites/{$this->invite->token}",
            ]);
    }
}

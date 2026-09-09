<?php

namespace App\Mail;

use App\Models\Organizer;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent after every successful password change - both from Profile (current
 * password required) and via the forgot-password reset link - so an
 * account holder finds out even if they weren't the one who changed it.
 * Standard "did you just do this?" security signal; there's nothing to
 * click or act on here since Sanctum tokens are already revoked by the
 * change itself (see OrganizerAuthController/ParticipantAuthController).
 * Not currently dispatched anywhere (pre-existing - true before the account
 * split too), kept as-is rather than wiring it up as part of this change.
 */
class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Organizer|Participant $user)
    {
    }

    public function build()
    {
        return $this->subject('Your QRMeets password was changed')
            ->view('emails.password-changed', ['user' => $this->user]);
    }
}

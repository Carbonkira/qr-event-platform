<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrganizationMemberJoinedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Organization $organization, public User $newMember)
    {
    }

    public function build()
    {
        $frontend = rtrim(config('services.frontend.url'), '/');

        return $this->subject("{$this->newMember->name} joined {$this->organization->name}")
            ->view('emails.organization-member-joined', [
                'organization' => $this->organization,
                'newMember' => $this->newMember,
                'membersUrl' => "{$frontend}/organizer/organizations",
            ]);
    }
}

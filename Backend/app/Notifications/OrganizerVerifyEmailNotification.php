<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Organizer and Participant can't share Laravel's default 'verification.verify'
 * named route - the same numeric id can exist in both tables at once, so the
 * link has to say which table to check. Otherwise identical to Laravel's own
 * VerifyEmail (queued for the same reason as the old VerifyEmailNotification).
 */
class OrganizerVerifyEmailNotification extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'organizer.verification.verify',
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );
    }
}

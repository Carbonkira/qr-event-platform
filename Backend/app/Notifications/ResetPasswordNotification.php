<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Points at the SPA's own reset-password page rather than Laravel's default
 * (a named 'password.reset' web route, which doesn't exist in this
 * API-only app) - see User::sendPasswordResetNotification().
 *
 * ShouldQueue matters here for the same reason as VerifyEmailNotification -
 * without it this sends inline during the forgot-password request, so a
 * slow/unreachable SMTP endpoint hangs that request instead of just
 * delaying an already-queued job.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $url)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your password')
            ->line('You requested a password reset. Click below to choose a new one.')
            ->action('Reset Password', $this->url)
            ->line('This link expires in 60 minutes. If you did not request this, no action is needed.');
    }
}

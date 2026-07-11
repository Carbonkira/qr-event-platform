<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's own VerifyEmail, unchanged - just queued. The base class isn't,
 * so User::sendEmailVerificationNotification() was sending it inline during
 * the register/resend-verification request. Confirmed in production: a slow
 * or unreachable SMTP endpoint hung those requests for 20+ seconds instead
 * of just delaying an already-queued job (see RegistrationConfirmedMail /
 * EventReminderMail, which were already ->queue()'d for the same reason).
 */
class VerifyEmailNotification extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;
}

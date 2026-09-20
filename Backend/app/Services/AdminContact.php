<?php

namespace App\Services;

use App\Models\Organizer;

/**
 * How an applicant reaches "the admin": the number they are told to call in
 * the application emails and on their status card, and the address a reply to
 * those emails goes to (their Reply-To - the mail itself is sent from
 * MAIL_FROM_ADDRESS, which is typically a sending-only address nobody reads).
 *
 * An explicit ADMIN_CONTACT_NUMBER / ADMIN_CONTACT_EMAIL env var wins (a
 * deliberately published office line or shared inbox); otherwise it's the
 * details of one admin account, so the owner can set them from inside the app
 * without a redeploy. Null when neither exists - the emails omit the line
 * rather than print a blank.
 */
class AdminContact
{
    public static function number(): ?string
    {
        $configured = config('services.admin.contact_number');
        if (filled($configured)) {
            return $configured;
        }

        return self::contactAdmin()?->contact_number;
    }

    public static function email(): ?string
    {
        $configured = config('services.admin.contact_email');
        if (filled($configured)) {
            return $configured;
        }

        return self::contactAdmin()?->email;
    }

    /**
     * The one admin who stands in for "the admin" when nothing is configured:
     * the first who has filled in a contact number, else simply the first.
     * Number and email come from the same person, so an applicant is never
     * told to call one admin and have their reply land with another.
     */
    private static function contactAdmin(): ?Organizer
    {
        return Organizer::where('role', 'admin')->whereNotNull('contact_number')->orderBy('id')->first()
            ?? Organizer::where('role', 'admin')->orderBy('id')->first();
    }
}

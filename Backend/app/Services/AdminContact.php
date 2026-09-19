<?php

namespace App\Services;

use App\Models\Organizer;

/**
 * The number applicants are told to call in the "we received your
 * application" emails. An explicit ADMIN_CONTACT_NUMBER env var wins (a
 * deliberately published number, e.g. an office line); otherwise it's
 * whichever admin account has filled in a contact number on their own
 * profile, so the owner can set it from inside the app without a redeploy.
 * Null when neither exists - the emails omit the line rather than print a
 * blank.
 */
class AdminContact
{
    public static function number(): ?string
    {
        $configured = config('services.admin.contact_number');
        if (filled($configured)) {
            return $configured;
        }

        return Organizer::where('role', 'admin')
            ->whereNotNull('contact_number')
            ->orderBy('id')
            ->value('contact_number');
    }
}

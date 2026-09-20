<?php

return [

    'defaults' => [
        'guard' => 'web',
        // Never actually resolved without an explicit broker name - both
        // AuthControllers always call Password::broker('organizers'|
        // 'participants') explicitly (see OrganizerAuthController/
        // ParticipantAuthController). Laravel just requires some default.
        'passwords' => 'organizers',
    ],

    // Sanctum registers its own 'sanctum' guard driver at boot time (used by
    // the 'auth:sanctum' middleware alias on protected API routes); it does
    // not need an explicit entry here. The 'web' guard is never actually used
    // (this is a token-only API) - Laravel just needs one to exist, so it's
    // pointed at organizers now that the old single-account 'users' provider
    // is gone.
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'organizers',
        ],
    ],

    'providers' => [
        'organizers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Organizer::class,
        ],
        'participants' => [
            'driver' => 'eloquent',
            'model' => App\Models\Participant::class,
        ],
    ],

    'passwords' => [
        'organizers' => [
            'provider' => 'organizers',
            'table' => 'organizer_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'participants' => [
            'provider' => 'participants',
            'table' => 'participant_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];

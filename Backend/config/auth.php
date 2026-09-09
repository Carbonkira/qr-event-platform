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
    // not need an explicit entry here.
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        // Legacy - App\Models\User now points at the renamed
        // legacy_users_backup table (see accounts:split-users), kept only
        // as a rollback source. Nothing in the running app resolves the
        // 'web' guard (see bootstrap/app.php), so this is harmless deadweight.
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
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

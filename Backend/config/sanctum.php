<?php

use Laravel\Sanctum\Sanctum;

return [

    // Unused by this app: we authenticate with plain Sanctum personal access
    // tokens (Bearer <token>, issued via createToken()), not the SPA cookie
    // flow, so 'stateful' domains / EnsureFrontendRequestsAreStateful never
    // come into play. Left populated for documentation purposes only.
    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:5173,127.0.0.1,127.0.0.1:8000,::1'
    )),

    'guard' => ['web'],

    // A stolen/leaked token used to stay valid forever - null never expired
    // it. 30 days by default: long enough that a returning organizer or
    // attendee won't notice, short enough to actually bound the exposure
    // window instead of it being permanent. This is a hard cutoff from
    // token creation (i.e. from login), not a sliding/rolling window -
    // see Laravel\Sanctum\Guard::supportsCredentials().
    'expiration' => env('SANCTUM_EXPIRATION', 60 * 24 * 30),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];

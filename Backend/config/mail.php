<?php

return [

    // 'log' by default - writes emails to storage/logs/laravel.log instead
    // of sending them, so the app runs with zero mail setup. For production,
    // set MAIL_MAILER=resend (see .env.example) - this hits Resend's HTTPS
    // API rather than opening a raw SMTP socket. That's not a style choice:
    // 'smtp' with Resend's own host/port genuinely failed in production
    // (Railway timed out connecting to ssl://smtp.resend.com:465) - lots of
    // PaaS hosts block or throttle outbound SMTP ports to fight spam abuse
    // from ephemeral containers, but a plain HTTPS request is never blocked.
    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
        'array' => [
            'transport' => 'array',
        ],
        'resend' => [
            'transport' => 'resend',
        ],
        // Kept for any provider that only offers SMTP - works fine on hosts
        // that don't block those ports, which Railway does not consistently.
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'QRMeets'),
    ],

];

<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        // Payment screenshots (RegistrationController::createRegistration,
        // Registration::getPaymentScreenshotUrlAttribute) always target this
        // disk by name, "public" - switching its driver to "s3" here (R2 is
        // S3-API-compatible) points them at Cloudflare R2 instead of local
        // disk with zero code changes, since local disk doesn't persist
        // across redeploys on most hosting.
        'public' => [
            'driver' => env('FILESYSTEM_PUBLIC_DRIVER', 'local'),
            // "root" means different things per driver: a local filesystem
            // path for "local", but a key prefix *inside the bucket* for
            // "s3" - reusing the local path there baked the whole absolute
            // path into every object's URL (.../app/storage/app/public/...).
            'root' => env('FILESYSTEM_PUBLIC_DRIVER') === 's3' ? '' : storage_path('app/public'),
            'url' => env('FILESYSTEM_PUBLIC_URL', env('APP_URL').'/storage'),
            'visibility' => 'public',
            'throw' => false,
            // Only read when the driver above is "s3":
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => true,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

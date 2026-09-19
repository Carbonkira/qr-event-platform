<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    // Gemini (Google Generative Language API) - server-side only. This key
    // is read exclusively from Backend/.env and is never sent in any API
    // response body; the frontend never sees it (only Vite-prefixed VITE_*
    // vars reach the browser bundle, and this isn't one). Used by
    // EventController::generateDescription and FeedbackSummaryController -
    // see App\Services\Gemini for the actual HTTP call.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    ],

    // Shown in the "we received your application" emails so an applicant
    // knows who to call. Optional - when unset, AdminContact falls back to
    // the contact number on an admin's own profile (editable in-app).
    'admin' => [
        'contact_number' => env('ADMIN_CONTACT_NUMBER'),
    ],

    // Where the SPA is deployed - used to build links inside emails
    // (email verification landing page, "view your pass" links, etc).
    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:5173'),
    ],

];

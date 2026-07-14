<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API-only backend: no session/CSRF middleware needed. Sanctum's
        // EnsureFrontendRequestsAreStateful (SPA cookie auth) is intentionally
        // NOT registered here - organizers authenticate with plain Sanctum
        // bearer tokens issued via createToken(), checked by the 'auth:sanctum'
        // middleware alias (registered by default in Laravel 11).
        $middleware->trustProxies(at: '*');

        // The frontend uses the original app's camelCase field names
        // (startTime, isPrivate, qrCode, ...) while Eloquent models use
        // snake_case columns. These two middleware convert at the API
        // boundary in both directions so neither side has to compromise.
        $middleware->api(append: [
            \App\Http\Middleware\ConvertCamelCaseRequestToSnakeCase::class,
            \App\Http\Middleware\ConvertSnakeCaseResponseToCamelCase::class,
            \App\Http\Middleware\AddSecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Validation errors are rendered by Laravel's exception handler,
        // which bypasses the ConvertSnakeCaseResponseToCamelCase middleware
        // (thrown exceptions skip the normal middleware "after" phase). Camel
        // -case the field-keyed `errors` object by hand so a 422's shape
        // matches every other response the frontend receives.
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $errors = collect($e->errors())
                ->mapWithKeys(fn ($messages, $field) => [\Illuminate\Support\Str::camel($field) => $messages])
                ->all();

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $errors,
            ], $e->status);
        });
    })->create();

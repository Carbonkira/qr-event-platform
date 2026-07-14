<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This API only ever returns JSON or redirects (see AuthController::verify)
 * - never renders untrusted HTML - so a full CSP isn't meaningful here
 * (that's handled on the frontend's own headers instead). These are the
 * cheap, unconditionally-safe ones: nothing on this API relies on being
 * framed, MIME-sniffed, or leaking a full referrer URL to a third party.
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}

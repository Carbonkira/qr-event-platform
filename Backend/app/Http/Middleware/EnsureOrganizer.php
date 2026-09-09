<?php

namespace App\Http\Middleware;

use App\Models\Organizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctum's polymorphic personal_access_tokens table happily authenticates
 * a valid token regardless of which account type it belongs to - a
 * Participant's token is just as "valid" as an Organizer's. This is the
 * check that actually closes that gap: a real participant account, with a
 * real valid token, still can't reach organizer-tooling routes.
 */
class EnsureOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof Organizer, 403, 'This action requires an organizer account.');

        return $next($request);
    }
}

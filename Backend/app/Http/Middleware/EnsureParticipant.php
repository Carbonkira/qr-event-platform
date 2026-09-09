<?php

namespace App\Http\Middleware;

use App\Models\Participant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * See EnsureOrganizer - same reasoning, the other direction: a valid
 * Organizer token shouldn't be able to reach purely-participant actions
 * like registering for someone else's event.
 */
class EnsureParticipant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof Participant, 403, 'This action requires a participant account.');

        return $next($request);
    }
}

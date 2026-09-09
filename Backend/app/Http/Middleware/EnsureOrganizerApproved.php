<?php

namespace App\Http\Middleware;

use App\Models\Organizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates organizer-tooling routes (event/org management, analytics, task
 * templates, ...) behind the admin-mediated approval in
 * Organizer::isOrganizerApproved() - deliberately not applied to routes any
 * logged-in, verified account can use regardless of organizer status
 * (registering for an event, discussion threads). Admins bypass the
 * approval check outright -
 * approve/reject itself has to stay reachable by an admin even in the edge
 * case of a manual forceFill that set role=admin without also setting
 * approval_status. Checks the account *type* itself first (a Participant
 * token is just as "validly authenticated" as an Organizer one, see
 * EnsureOrganizer) rather than relying on route middleware ordering to have
 * already ruled that out - self-contained on purpose.
 */
class EnsureOrganizerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof Organizer, 403, 'This action requires an organizer account.');

        abort_unless(
            $request->user()->isAdmin() || $request->user()->isOrganizerApproved(),
            403,
            'Your organizer account is still awaiting admin approval.'
        );

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use Illuminate\Http\Request;

/**
 * Admin-mediated organizer approval - a prospective organizer meets with the
 * system admin in person, who then approves or rejects the account here.
 * Modeled directly on EventController::approve()/reject() (same
 * authorizeAdmin() idiom, same plain status-flip shape), just operating on
 * Organizer::$approval_status instead of Event::$status.
 */
class OrganizerApprovalController extends Controller
{
    public function pending(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json(
            Organizer::with('requestedOrganization:id,name')
                ->where('approval_status', 'pending')
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function approve(Request $request, Organizer $user)
    {
        $this->authorizeAdmin($request);

        // None of these are mass-assignable (see Organizer::$fillable) -
        // same reason role/email_verified_at get forceFill'd everywhere
        // else in this codebase, so update() here would silently no-op
        // instead of actually approving anyone.
        $user->forceFill([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ])->save();

        // Approving the account also grants the organization membership they
        // asked for at signup (if any) - same 'member' role an invite
        // acceptance grants, see InviteController::accept().
        if ($user->requested_organization_id) {
            $user->organizations()->syncWithoutDetaching([$user->requested_organization_id => ['role' => 'member']]);
        }

        return response()->json($user);
    }

    public function reject(Request $request, Organizer $user)
    {
        $this->authorizeAdmin($request);

        $user->forceFill([
            'approval_status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ])->save();

        return response()->json($user);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only an admin can do that.');
    }
}

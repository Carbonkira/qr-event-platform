<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Organizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                ->where('role', '!=', 'admin')
                ->orderByDesc('created_at')
                ->get()
        );
    }

    /**
     * Every organizer application an admin has already decided on, newest
     * decision first - the "accepted and rejected over time" half of the
     * Approvals page. Accounts grandfathered in when approval was introduced
     * have no approved_at (nobody actually reviewed them), so they're not
     * history; admins are excluded for the same reason.
     */
    public function history(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json(
            Organizer::with(['requestedOrganization:id,name', 'approver:id,name'])
                ->where('role', '!=', 'admin')
                ->whereIn('approval_status', ['approved', 'rejected'])
                ->whereNotNull('approved_at')
                ->orderByDesc('approved_at')
                ->get()
        );
    }

    /**
     * `create_organization` is for an applicant who asked for an organization
     * that doesn't exist yet (requested_organization_name): the admin can
     * create it right here and make the applicant its owner in the same
     * step, instead of approving, then hopping to the Organizations page to
     * build it by hand. Ignored when the applicant picked an existing one.
     */
    public function approve(Request $request, Organizer $user)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'create_organization' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $user, $data) {
            // None of these are mass-assignable (see Organizer::$fillable) -
            // same reason role/email_verified_at get forceFill'd everywhere
            // else in this codebase, so update() here would silently no-op
            // instead of actually approving anyone.
            $user->forceFill([
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ])->save();

            if (! empty($data['create_organization']) && ! $user->requested_organization_id && $user->requested_organization_name) {
                $organization = Organization::create([
                    'name' => $user->requested_organization_name,
                    'slug' => Organization::uniqueSlug($user->requested_organization_name),
                    'address' => $user->requested_organization_address,
                ]);
                $organization->members()->attach($user->id, ['role' => 'owner']);
                $user->forceFill(['requested_organization_id' => $organization->id])->save();
            } elseif ($user->requested_organization_id) {
                // Approving the account also grants the organization
                // membership they asked for at signup - same 'member' role an
                // invite acceptance grants, see InviteController::accept().
                $user->organizations()->syncWithoutDetaching([$user->requested_organization_id => ['role' => 'member']]);
            }
        });

        return response()->json($user->fresh('requestedOrganization'));
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

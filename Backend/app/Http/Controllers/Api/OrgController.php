<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrganizationInviteMail;
use App\Models\Organization;
use App\Models\OrganizationInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Real multi-organization CRUD. The old singleton OrganizationController
 * (a single global-branding Organization row, shown everywhere regardless
 * of who was actually hosting) has been removed - AppShell's footer,
 * Profile.jsx, LandingHero.jsx, and Register.jsx all use real per-org data
 * now (or, for the platform-wide footer, no org data at all - there's no
 * single "the" organization to represent anymore).
 */
class OrgController extends Controller
{
    private const PROFILE_FIELDS = [
        'name' => ['sometimes', 'string', 'max:255'],
        'description' => ['sometimes', 'nullable', 'string'],
        'organized_by' => ['sometimes', 'nullable', 'string', 'max:255'],
        'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
        'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
        'instagram' => ['sometimes', 'nullable', 'string', 'max:255'],
        'linkedin' => ['sometimes', 'nullable', 'string', 'max:255'],
        'facebook' => ['sometimes', 'nullable', 'string', 'max:255'],
        'website' => ['sometimes', 'nullable', 'string', 'max:255'],
        'twitter' => ['sometimes', 'nullable', 'string', 'max:255'],
        'privacy_policy_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
    ];

    /** Organizations the current user belongs to, with their role in each. */
    public function mine(Request $request)
    {
        return response()->json($request->user()->organizations()->get());
    }

    /**
     * Public organization page - branding plus its public events, split
     * into upcoming/past like the mock's participant-facing pages. Only
     * approved, non-private events are shown here, same visibility rule as
     * EventController::index's public listing.
     */
    /**
     * Public directory - lets anyone browse organizations the way they'd
     * browse events, not just land on one via a direct /org/:slug link.
     * Only orgs with at least one real public event are listed, so a stub
     * org someone created and never used doesn't clutter the directory.
     */
    public function directory()
    {
        $organizations = Organization::whereHas('events', function ($query) {
            $query->where('status', 'approved')->where('is_private', false);
        })
            ->withCount(['events as upcoming_events_count' => function ($query) {
                $query->where('status', 'approved')->where('is_private', false)->where('date', '>=', now()->toDateString());
            }])
            ->orderBy('name')
            ->get();

        return response()->json($organizations);
    }

    public function showPublic(Organization $organization)
    {
        $events = $organization->events()
            ->where('status', 'approved')
            ->where('is_private', false)
            ->orderBy('date')
            ->get();

        $today = now()->toDateString();

        return response()->json([
            'organization' => $organization,
            'upcomingEvents' => $events->where('date', '>=', $today)->values(),
            'pastEvents' => $events->where('date', '<', $today)->sortByDesc('date')->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge(self::PROFILE_FIELDS, [
            'name' => ['required', 'string', 'max:255'],
        ]));

        $org = Organization::create(array_merge($data, [
            'slug' => $this->uniqueSlug($data['name']),
        ]));
        $org->members()->attach($request->user()->id, ['role' => 'owner']);

        return response()->json($org, 201);
    }

    public function update(Request $request, Organization $organization)
    {
        $this->authorizeOwner($request, $organization);

        $data = $request->validate(self::PROFILE_FIELDS);
        $organization->update($data);

        return response()->json($organization);
    }

    public function uploadLogo(Request $request, Organization $organization)
    {
        $this->authorizeOwner($request, $organization);

        $request->validate([
            'logo' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('logo')->store('org-logos', 'public');
        $organization->update(['logo' => Storage::disk('public')->url($path)]);

        return response()->json($organization);
    }

    /** Members of the organization, with their role - visible to any member. */
    public function members(Request $request, Organization $organization)
    {
        abort_unless($organization->isMember($request->user()), 403, 'You are not a member of this organization.');

        return response()->json($organization->members()->get());
    }

    /** Owner-only: remove a member. The last owner can't be removed (an org must always have one). */
    public function removeMember(Request $request, Organization $organization, User $user)
    {
        $this->authorizeOwner($request, $organization);

        $isRemovingLastOwner = $organization->isOwner($user)
            && $organization->members()->wherePivot('role', 'owner')->count() === 1;
        abort_if($isRemovingLastOwner, 422, "An organization must always have at least one owner.");

        $organization->members()->detach($user->id);

        return response()->json(['message' => 'Member removed.']);
    }

    /** Owner-only: pending (not yet accepted) invites for the organization. */
    public function invites(Request $request, Organization $organization)
    {
        $this->authorizeOwner($request, $organization);

        return response()->json($organization->invites()->whereNull('accepted_at')->latest()->get());
    }

    /** Owner-only: invite someone by email. They don't need an account yet. */
    public function storeInvite(Request $request, Organization $organization)
    {
        $this->authorizeOwner($request, $organization);

        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $invite = $organization->invites()->create([
            'email' => $data['email'],
            'token' => Str::random(64),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invite->email)->queue(new OrganizationInviteMail($invite));

        return response()->json($invite, 201);
    }

    /** Owner-only: revoke a pending invite. */
    public function destroyInvite(Request $request, Organization $organization, OrganizationInvite $invite)
    {
        $this->authorizeOwner($request, $organization);
        abort_unless($invite->organization_id === $organization->id, 404);

        $invite->delete();

        return response()->json(['message' => 'Invite revoked.']);
    }

    private function authorizeOwner(Request $request, Organization $organization): void
    {
        abort_unless($organization->isOwner($request->user()), 403, 'Only an owner of this organization can do that.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';
        $slug = $base;
        $suffix = 1;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}

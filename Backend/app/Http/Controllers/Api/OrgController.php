<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Real multi-organization CRUD - deliberately a separate controller from
 * the old singleton OrganizationController (still used as-is by AppShell's
 * footer, Profile.jsx, LandingHero.jsx, and Register.jsx, which all show
 * generic global branding). Those get migrated over to real per-user org
 * data in a later phase; until then, both controllers coexist safely.
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

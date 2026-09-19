<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A real, multi-member organization - a user can belong to zero, one, or
 * several (see OrganizationMember). `slug` is nullable at the DB level only
 * for migration safety (backfilling pre-existing rows); every organization
 * created going forward always gets one (see OrgController::store()).
 */
class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'organized_by',
        'email',
        'address',
        'industry',
        'instagram',
        'linkedin',
        'facebook',
        'website',
        'twitter',
        'privacy_policy_url',
        'logo',
    ];

    // Address is only there for the admin to verify an organization is
    // real - it must never reach a public response (showPublic(),
    // directory(), the org embedded in an event). OrgController's
    // authenticated endpoints opt back in with makeVisible().
    protected $hidden = ['address'];

    /** Slug from a name, suffixed until it doesn't collide with an existing organization. */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Organizer::class, 'organization_members', 'organization_id', 'organizer_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(OrganizationInvite::class);
    }

    public function discussionThreads(): HasMany
    {
        return $this->hasMany(DiscussionThread::class);
    }

    public function isMember(Organizer $organizer): bool
    {
        return $this->members()->where('organizers.id', $organizer->id)->exists();
    }

    public function isOwner(Organizer $organizer): bool
    {
        return $this->members()->where('organizers.id', $organizer->id)->wherePivot('role', 'owner')->exists();
    }
}

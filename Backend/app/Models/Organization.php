<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A real, multi-member organization - a user can belong to zero, one, or
 * several (see OrganizationMember). `slug` is nullable at the DB level only
 * for migration safety (backfilling pre-existing rows); every organization
 * created going forward always gets one (see OrganizationController::store()).
 */
class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'organized_by',
        'email',
        'industry',
        'instagram',
        'linkedin',
        'facebook',
        'website',
        'twitter',
        'privacy_policy_url',
        'logo',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
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

    public function isMember(User $user): bool
    {
        return $this->members()->where('users.id', $user->id)->exists();
    }

    public function isOwner(User $user): bool
    {
        return $this->members()->where('users.id', $user->id)->wherePivot('role', 'owner')->exists();
    }
}

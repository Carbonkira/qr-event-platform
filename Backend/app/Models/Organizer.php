<?php

namespace App\Models;

use App\Notifications\OrganizerVerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * One half of the adviser-required account split (see Participant for the
 * other) - an Organizer can host events and belong to zero, one, or several
 * Organizations (see organizations()), but can never register for an event
 * (that's exclusively a Participant action, see Registration::participant()).
 * `role` is a separate, platform-wide tier: 'organizer' (the default) vs
 * 'admin' (see isAdmin()). `approval_status` gates organizer tooling
 * specifically (see EnsureOrganizerApproved) - a prospective organizer has
 * to meet with the system admin before this flips to 'approved'. Neither
 * `role` nor `approval_status` is mass-assignable - both are only ever set
 * via forceFill() (OrganizerApprovalController, seeders/tests, or the
 * accounts:split-users backfill's grandfathering).
 */
class Organizer extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'institution',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOrganizerApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members', 'organizer_id', 'organization_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedOrganizationIds(): array
    {
        return $this->organizations()->wherePivot('role', 'owner')->pluck('organizations.id')->all();
    }

    /**
     * Points the reset link at the SPA instead of Laravel's default (a
     * named 'password.reset' web route, which doesn't exist here).
     */
    public function sendPasswordResetNotification($token): void
    {
        $frontend = rtrim(config('services.frontend.url'), '/');
        $url = "{$frontend}/reset-password?token={$token}&email=".urlencode($this->email)."&type=organizer";

        $this->notify(new ResetPasswordNotification($url));
    }

    /**
     * Overrides the MustVerifyEmail trait's default only to swap in the
     * queued notification below - everything else (signed URL, mail
     * content) is unchanged from Laravel's own VerifyEmail.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new OrganizerVerifyEmailNotification);
    }
}

<?php

namespace App\Models;

use App\Notifications\ParticipantVerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The other half of the adviser-required account split (see Organizer) - a
 * Participant can register for events, but can never host one; there's no
 * organizer tooling a Participant account can reach (see
 * EnsureParticipant/EnsureOrganizer).
 */
class Participant extends Authenticatable implements MustVerifyEmail
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
            'password' => 'hashed',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'participant_id');
    }

    /**
     * Points the reset link at the SPA instead of Laravel's default (a
     * named 'password.reset' web route, which doesn't exist here).
     */
    public function sendPasswordResetNotification($token): void
    {
        $frontend = rtrim(config('services.frontend.url'), '/');
        $url = "{$frontend}/reset-password?token={$token}&email=".urlencode($this->email)."&type=participant";

        $this->notify(new ResetPasswordNotification($url));
    }

    /**
     * Overrides the MustVerifyEmail trait's default only to swap in the
     * queued notification below - everything else (signed URL, mail
     * content) is unchanged from Laravel's own VerifyEmail.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new ParticipantVerifyEmailNotification);
    }
}

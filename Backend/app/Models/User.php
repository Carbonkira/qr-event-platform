<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A single account type shared by organizers and participants - anyone can
 * both host events (see Event::user()) and register for them (see
 * Registration::user()). The only permission tier is `role`: 'organizer'
 * (the default - can do everything except approve/reject events) vs 'admin'
 * (see EventController::authorizeAdmin). Nothing lets an account promote
 * itself to admin - that's a manual/seeded assignment only.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'institution',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

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

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Points the reset link at the SPA instead of Laravel's default (a
     * named 'password.reset' web route, which doesn't exist here).
     */
    public function sendPasswordResetNotification($token): void
    {
        $frontend = rtrim(config('services.frontend.url'), '/');
        $url = "{$frontend}/reset-password?token={$token}&email=".urlencode($this->email);

        $this->notify(new ResetPasswordNotification($url));
    }
}

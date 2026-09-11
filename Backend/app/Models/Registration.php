<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Registration extends Model
{
    protected $fillable = [
        'event_id',
        'participant_id',
        'name',
        'email',
        'custom_data',
        'qr_code',
        'attended',
        'check_in_time',
        'feedback_submitted',
        'is_walk_in',
        'waitlisted',
        'payment_status',
        'payment_ref',
        'payment_screenshot',
        'reminder_sent_at',
    ];

    // Deliberately not fillable - a client should never be able to set or
    // overwrite its own pass_token. Every new registration gets one
    // automatically; see the pass_token migration for why this exists.
    protected static function booted(): void
    {
        static::creating(function (Registration $registration) {
            $registration->pass_token ??= Str::random(48);
        });
    }

    // Exposed as paymentScreenshotUrl (camelCased at the response boundary)
    // so the frontend never needs to know the storage disk/path scheme.
    // The raw storage path itself is hidden - only the computed URL matters.
    protected $appends = ['payment_screenshot_url'];
    protected $hidden = ['payment_screenshot'];

    public function getPaymentScreenshotUrlAttribute(): ?string
    {
        return $this->payment_screenshot ? Storage::disk('public')->url($this->payment_screenshot) : null;
    }

    protected function casts(): array
    {
        return [
            'custom_data' => 'array',
            'attended' => 'boolean',
            'check_in_time' => 'datetime',
            'feedback_submitted' => 'boolean',
            'is_walk_in' => 'boolean',
            'waitlisted' => 'boolean',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}

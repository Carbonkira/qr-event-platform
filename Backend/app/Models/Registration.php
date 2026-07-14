<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Registration extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'name',
        'email',
        'custom_data',
        'qr_code',
        'attended',
        'check_in_time',
        'feedback_submitted',
        'is_walk_in',
        'needs_certificate',
        'waitlisted',
        'payment_status',
        'payment_ref',
        'payment_screenshot',
        'reminder_sent_at',
    ];

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
            'needs_certificate' => 'boolean',
            'waitlisted' => 'boolean',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}

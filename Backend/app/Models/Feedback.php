<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    // Explicit table name: Eloquent would otherwise pluralize this to
    // "feedbacks", but the migration (and the plan's §1 table) names it
    // "feedback" (uncountable, matching the mock's `feedback` array).
    protected $table = 'feedback';

    protected $fillable = [
        'registration_id',
        'event_id',
        'q1',
        'q2',
        'q3',
        'q4',
        'q5',
        'comment',
        'custom_answers',
        'is_highlighted',
        'badge',
    ];

    protected function casts(): array
    {
        return [
            'q1' => 'integer',
            'q2' => 'integer',
            'q3' => 'integer',
            'q4' => 'integer',
            'q5' => 'integer',
            'custom_answers' => 'array',
            'is_highlighted' => 'boolean',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

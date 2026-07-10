<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'title',
        'type',
        'is_private',
        'slug',
        'private_link',
        'description',
        'venue',
        'location',
        'lat',
        'lng',
        'date',
        'start_time',
        'end_time',
        'organized_by',
        'industry',
        'capacity',
        'status',
        'feedback_enabled',
        'image',
        'requires_certificate',
        'pricing',
        'price',
        'allow_walk_ins',
        'socials',
        'privacy_policy_url',
        'custom_fields',
        'feedback_questions',
        'tags',
        'ai_summary',
        'ai_summary_generated_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'date' => 'date',
            'capacity' => 'integer',
            'feedback_enabled' => 'boolean',
            'requires_certificate' => 'boolean',
            'price' => 'decimal:2',
            'allow_walk_ins' => 'boolean',
            'socials' => 'array',
            'custom_fields' => 'array',
            'feedback_questions' => 'array',
            'tags' => 'array',
            'ai_summary_generated_at' => 'datetime',
        ];
    }

    /**
     * The 5 built-in rating questions, used whenever an event hasn't
     * customized its feedback form. Kept in sync with the fixed q1-q5
     * columns on the feedback table - "rating" questions here always map
     * 1:1 to q1..q5 in that order; any additional "rating" or "text"
     * entries beyond the first 5 are extra questions answered into
     * feedback.custom_answers instead.
     */
    public static function defaultFeedbackQuestions(): array
    {
        return [
            ['id' => 'q1', 'label' => 'Check-in experience', 'type' => 'rating', 'required' => true],
            ['id' => 'q2', 'label' => 'Event organization', 'type' => 'rating', 'required' => true],
            ['id' => 'q3', 'label' => 'Content quality', 'type' => 'rating', 'required' => true],
            ['id' => 'q4', 'label' => 'Venue & facilities', 'type' => 'rating', 'required' => true],
            ['id' => 'q5', 'label' => 'Overall satisfaction', 'type' => 'rating', 'required' => true],
        ];
    }

    public function feedbackQuestionsOrDefault(): array
    {
        return $this->feedback_questions ?: self::defaultFeedbackQuestions();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}

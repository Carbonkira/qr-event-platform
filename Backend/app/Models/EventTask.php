<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTask extends Model
{
    protected $fillable = [
        'event_id',
        'label',
        'done',
    ];

    protected function casts(): array
    {
        return [
            'done' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

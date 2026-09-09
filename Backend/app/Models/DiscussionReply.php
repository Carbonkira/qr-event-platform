<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** See DiscussionThread - same dual-author-column reasoning. */
class DiscussionReply extends Model
{
    protected $fillable = ['thread_id', 'organizer_user_id', 'participant_user_id', 'body'];

    protected $appends = ['author'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(DiscussionThread::class, 'thread_id');
    }

    public function organizerAuthor(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_user_id');
    }

    public function participantAuthor(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_user_id');
    }

    public function getAuthorAttribute(): ?array
    {
        $author = $this->organizerAuthor ?? $this->participantAuthor;

        return $author ? ['id' => $author->id, 'name' => $author->name, 'avatar' => $author->avatar] : null;
    }
}

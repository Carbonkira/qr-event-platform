<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Open to org members and event registrants alike (see DiscussionController)
 * - the author can be either an Organizer or a Participant, so this carries
 * two nullable typed FKs instead of one, with a DB-level CHECK constraint
 * enforcing exactly one is ever set (see the migration). Deliberately not a
 * polymorphic morphTo - there are only ever two possible author kinds, and
 * every other relation in this codebase uses plain typed FKs, not morphs.
 */
class DiscussionThread extends Model
{
    protected $fillable = ['organization_id', 'organizer_user_id', 'participant_user_id', 'title', 'body'];

    protected $appends = ['author'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizerAuthor(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_user_id');
    }

    public function participantAuthor(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_user_id');
    }

    /**
     * Whichever of organizerAuthor/participantAuthor is actually set - the
     * CHECK constraint guarantees exactly one is. Callers eager-load both
     * relations (see DiscussionController) so this never triggers a lazy
     * query of its own, just picks whichever loaded relation is non-null.
     */
    public function getAuthorAttribute(): ?array
    {
        $author = $this->organizerAuthor ?? $this->participantAuthor;

        return $author ? ['id' => $author->id, 'name' => $author->name, 'avatar' => $author->avatar] : null;
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionReply::class, 'thread_id');
    }
}

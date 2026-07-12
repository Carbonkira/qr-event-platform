<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Directional while pending (the request); once accepted, either side is
 * "connected" regardless of who sent it - callers should use the
 * involving() scope rather than querying requester_id/recipient_id
 * directly, since a pair could be stored in either direction.
 */
class Connection extends Model
{
    protected $fillable = ['requester_id', 'recipient_id', 'status'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** Any row (in either direction) between these two users. */
    public function scopeBetween(Builder $query, int $userIdA, int $userIdB): Builder
    {
        return $query->where(function ($q) use ($userIdA, $userIdB) {
            $q->where(['requester_id' => $userIdA, 'recipient_id' => $userIdB])
                ->orWhere(['requester_id' => $userIdB, 'recipient_id' => $userIdA]);
        });
    }

    /** Every row (in either direction) touching this user. */
    public function scopeInvolving(Builder $query, int $userId): Builder
    {
        return $query->where('requester_id', $userId)->orWhere('recipient_id', $userId);
    }

    public function otherUser(int $selfId): User
    {
        return $this->requester_id === $selfId ? $this->recipient : $this->requester;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscussionThread;
use App\Models\Organization;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Http\Request;

/**
 * Per-organization discussion board - async, message-board style (no
 * real-time infrastructure exists in this app). Open to any org member
 * (an Organizer), or anyone who's registered (registered *or* attended -
 * deliberately not attended-only, so people can discuss an upcoming event
 * beforehand) for one of the organization's events (a Participant). The one
 * route both account types can reach - see authorizeAccess().
 */
class DiscussionController extends Controller
{
    private const AUTHOR_RELATIONS = ['organizerAuthor:id,name,avatar', 'participantAuthor:id,name,avatar'];

    public function index(Request $request, Organization $organization)
    {
        $this->authorizeAccess($request->user(), $organization);

        return response()->json(
            $organization->discussionThreads()->with(self::AUTHOR_RELATIONS)
                ->withCount('replies')
                ->latest()
                ->get()
        );
    }

    public function store(Request $request, Organization $organization)
    {
        $this->authorizeAccess($request->user(), $organization);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $thread = $organization->discussionThreads()->create([
            ...$data,
            ...$this->authorColumn($request->user()),
        ]);

        return response()->json($thread->load(self::AUTHOR_RELATIONS), 201);
    }

    public function show(Request $request, DiscussionThread $thread)
    {
        $this->authorizeAccess($request->user(), $thread->organization);

        return response()->json(
            $thread->load([...self::AUTHOR_RELATIONS, 'replies.organizerAuthor:id,name,avatar', 'replies.participantAuthor:id,name,avatar'])
        );
    }

    public function storeReply(Request $request, DiscussionThread $thread)
    {
        $this->authorizeAccess($request->user(), $thread->organization);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $reply = $thread->replies()->create([
            ...$data,
            ...$this->authorColumn($request->user()),
        ]);

        return response()->json($reply->load(self::AUTHOR_RELATIONS), 201);
    }

    /** Sets exactly one of the two author columns, matching the account type actually posting. */
    private function authorColumn(Organizer|Participant $user): array
    {
        return $user instanceof Organizer
            ? ['organizer_user_id' => $user->id, 'participant_user_id' => null]
            : ['organizer_user_id' => null, 'participant_user_id' => $user->id];
    }

    private function authorizeAccess(Organizer|Participant $user, Organization $organization): void
    {
        $canAccess = $user instanceof Organizer
            ? $organization->isMember($user)
            : Registration::whereHas('event', fn ($q) => $q->where('organization_id', $organization->id))
                ->where('participant_id', $user->id)
                ->exists();

        abort_unless($canAccess, 403, 'Only members or registrants of this organization can access its discussion board.');
    }
}

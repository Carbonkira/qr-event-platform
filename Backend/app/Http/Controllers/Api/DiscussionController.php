<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscussionThread;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Per-organization discussion board - async, message-board style (no
 * real-time infrastructure exists in this app). Open to any org member,
 * or anyone who's registered (registered *or* attended - deliberately not
 * attended-only, so people can discuss an upcoming event beforehand) for
 * one of the organization's events.
 */
class DiscussionController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        $this->authorizeAccess($request->user(), $organization);

        return response()->json(
            $organization->discussionThreads()->with('author:id,name,avatar')
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
            'user_id' => $request->user()->id,
        ]);

        return response()->json($thread->load('author:id,name,avatar'), 201);
    }

    public function show(Request $request, DiscussionThread $thread)
    {
        $this->authorizeAccess($request->user(), $thread->organization);

        return response()->json(
            $thread->load(['author:id,name,avatar', 'replies.author:id,name,avatar'])
        );
    }

    public function storeReply(Request $request, DiscussionThread $thread)
    {
        $this->authorizeAccess($request->user(), $thread->organization);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $reply = $thread->replies()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($reply->load('author:id,name,avatar'), 201);
    }

    private function authorizeAccess(User $user, Organization $organization): void
    {
        $canAccess = $organization->isMember($user)
            || Registration::whereHas('event', fn ($q) => $q->where('organization_id', $organization->id))
                ->where('user_id', $user->id)
                ->exists();

        abort_unless($canAccess, 403, 'Only members or registrants of this organization can access its discussion board.');
    }
}

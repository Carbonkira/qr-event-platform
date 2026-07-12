<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ConnectionRequestMail;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Real mutual connections - a request/accept relationship, not just a
 * passive "who's attending" list. Directional while pending (see Connection
 * model); either party sees an accepted row as "connected" regardless of
 * who originally sent the request.
 */
class ConnectionController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user()->id;
        $rows = Connection::involving($me)->with(['requester:id,name,avatar', 'recipient:id,name,avatar'])->get();

        return response()->json([
            'accepted' => $rows->where('status', 'accepted')
                ->map(fn (Connection $c) => ['id' => $c->id, 'user' => $c->otherUser($me)])->values(),
            'incoming' => $rows->where('status', 'pending')->where('recipient_id', $me)
                ->map(fn (Connection $c) => ['id' => $c->id, 'user' => $c->requester])->values(),
            'outgoing' => $rows->where('status', 'pending')->where('requester_id', $me)
                ->map(fn (Connection $c) => ['id' => $c->id, 'user' => $c->recipient])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['recipient_id' => ['required', 'integer', 'exists:users,id']]);
        $me = $request->user()->id;
        abort_if($data['recipient_id'] == $me, 422, "You can't connect with yourself.");

        $existing = Connection::between($me, $data['recipient_id'])->first();
        if ($existing) {
            abort_if($existing->status === 'accepted', 422, 'You are already connected.');
            abort_if($existing->status === 'pending', 422, 'A connection request is already pending.');

            // Declined requests are re-requestable - reset the row as a
            // fresh request from whoever's asking now, rather than leaving
            // a permanently dead row blocked by the unique constraint.
            $existing->update(['requester_id' => $me, 'recipient_id' => $data['recipient_id'], 'status' => 'pending']);
            $connection = $existing->fresh();
        } else {
            $connection = Connection::create(['requester_id' => $me, 'recipient_id' => $data['recipient_id'], 'status' => 'pending']);
        }

        Mail::to($connection->recipient->email)->queue(new ConnectionRequestMail($connection));

        return response()->json($connection, 201);
    }

    public function accept(Request $request, Connection $connection)
    {
        abort_unless($connection->recipient_id === $request->user()->id, 403, 'Only the recipient can accept this request.');
        abort_unless($connection->status === 'pending', 422, 'This request is no longer pending.');

        $connection->update(['status' => 'accepted']);

        return response()->json($connection);
    }

    public function decline(Request $request, Connection $connection)
    {
        abort_unless($connection->recipient_id === $request->user()->id, 403, 'Only the recipient can decline this request.');
        abort_unless($connection->status === 'pending', 422, 'This request is no longer pending.');

        $connection->update(['status' => 'declined']);

        return response()->json($connection);
    }

    public function destroy(Request $request, Connection $connection)
    {
        $me = $request->user()->id;
        abort_unless($connection->requester_id === $me || $connection->recipient_id === $me, 403);

        $connection->delete();

        return response()->json(['message' => 'Removed.']);
    }
}

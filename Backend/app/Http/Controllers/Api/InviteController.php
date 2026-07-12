<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrganizationMemberJoinedMail;
use App\Models\OrganizationInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InviteController extends Controller
{
    /** Public - lets the invite-acceptance page show what the invite is for before login/signup. */
    public function show(string $token)
    {
        $invite = OrganizationInvite::where('token', $token)->firstOrFail();

        return response()->json([
            'email' => $invite->email,
            'organization' => $invite->organization()->select('id', 'name', 'slug', 'logo')->first(),
            'inviter_name' => $invite->inviter->name,
            'expired' => $invite->isExpired(),
            'accepted' => $invite->accepted_at !== null,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invite = OrganizationInvite::where('token', $token)->firstOrFail();

        abort_if($invite->accepted_at !== null, 422, 'This invite has already been used.');
        abort_if($invite->isExpired(), 422, 'This invite has expired.');
        abort_unless(
            strcasecmp($invite->email, $request->user()->email) === 0,
            403,
            'This invite was sent to a different email address.'
        );

        $invite->organization->members()->syncWithoutDetaching([$request->user()->id => ['role' => 'member']]);
        $invite->update(['accepted_at' => now()]);

        $owners = $invite->organization->members()->wherePivot('role', 'owner')->get();
        foreach ($owners as $owner) {
            Mail::to($owner->email)->queue(new OrganizationMemberJoinedMail($invite->organization, $request->user()));
        }

        return response()->json($invite->organization);
    }
}

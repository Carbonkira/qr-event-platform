<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationConfirmedMail;
use App\Models\Connection;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RegistrationController extends Controller
{
    /**
     * Register for an event. Requires an account (routes/api.php puts this
     * behind auth:sanctum) - the registrant is always request->user(), so
     * "registering" and "creating an account" are the same action from the
     * frontend's point of view (see AuthController::register). Walk-ins are
     * the one exception; they go through walkIn() below instead.
     */
    public function store(Request $request, Event $event)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'custom_data' => ['sometimes', 'nullable', 'array'],
            'needs_certificate' => ['sometimes', 'boolean'],
            'payment_ref' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_screenshot' => ['sometimes', 'nullable', 'image', 'max:5120'], // 5MB, matches the frontend's own limit
        ]);

        // Re-registering (double submit, revisiting the register page after
        // already signing up) used to create a second Registration row with
        // its own QR code - each independently scannable, silently inflating
        // attendance for what's really one person. Confirmed in live
        // testing: a single attendee's repeat registrations accounted for
        // 3 of 5 "unique" check-ins at one test event.
        $existing = Registration::where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json($existing, 200);
        }

        $registration = $this->createRegistration($event, $data, $request, [
            'user_id' => $request->user()->id,
            'is_walk_in' => false,
        ]);

        return response()->json($registration, 201);
    }

    /**
     * Self-serve on-site check-in - no account, instantly marked attended.
     * Kept public/unauthenticated to match a walk-up kiosk (a phone/tablet
     * at the door, nobody logged in) rather than the pre-event RSVP flow.
     */
    public function walkIn(Request $request, Event $event)
    {
        abort_unless($event->allow_walk_ins, 422, 'This event does not accept walk-ins.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'custom_data' => ['sometimes', 'nullable', 'array'],
            'needs_certificate' => ['sometimes', 'boolean'],
        ]);

        $registration = $this->createRegistration($event, $data, $request, [
            'user_id' => null,
            'is_walk_in' => true,
            'attended' => true,
            'check_in_time' => now(),
        ]);

        return response()->json($registration, 201);
    }

    /**
     * Organizer adding a guest by hand (phone/email registration, a plus-one,
     * fixing a typo'd signup) - the guest doesn't need an account for this.
     */
    public function addGuest(Request $request, Event $event)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'custom_data' => ['sometimes', 'nullable', 'array'],
            'needs_certificate' => ['sometimes', 'boolean'],
            'attended' => ['sometimes', 'boolean'],
        ]);

        $registration = $this->createRegistration($event, $data, $request, [
            'user_id' => null,
            'is_walk_in' => false,
            'attended' => $data['attended'] ?? false,
            'check_in_time' => ($data['attended'] ?? false) ? now() : null,
        ]);

        return response()->json($registration, 201);
    }

    /**
     * Ports the mock's addRegistration() exactly (App.jsx:100-105): QR code
     * format is "QR-E{eventNum}-{P|WI}{seq}", where eventNum is the event's
     * id zero-padded to 3 digits and seq is a 1-based, per-event,
     * per-registration-type-agnostic running count (the mock counts *all*
     * registrations for the event, walk-in or not, so P and WI sequences
     * share one counter here too).
     */
    private function createRegistration(Event $event, array $data, Request $request, array $overrides): Registration
    {
        $isWalkIn = $overrides['is_walk_in'] ?? false;

        $seq = $this->nextSequence($event);
        $eventNum = str_pad((string) $event->id, 3, '0', STR_PAD_LEFT);
        $prefix = $isWalkIn ? 'WI' : 'P';
        $qrCode = sprintf('QR-E%s-%s%s', $eventNum, $prefix, str_pad((string) $seq, 3, '0', STR_PAD_LEFT));

        $paymentRef = $data['payment_ref'] ?? null;

        $screenshotPath = null;
        if ($request->hasFile('payment_screenshot')) {
            $screenshotPath = $request->file('payment_screenshot')->store('payment-screenshots', 'public');
        }

        $registration = $event->registrations()->create(array_merge([
            'name' => $data['name'],
            'email' => $data['email'],
            'custom_data' => $data['custom_data'] ?? [],
            'qr_code' => $qrCode,
            'attended' => false,
            'check_in_time' => null,
            'feedback_submitted' => false,
            'needs_certificate' => $data['needs_certificate'] ?? false,
            'waitlisted' => ! $isWalkIn && $this->isEventFull($event),
            'payment_status' => $paymentRef ? 'pending' : null,
            'payment_ref' => $paymentRef,
            'payment_screenshot' => $screenshotPath,
        ], $overrides));

        // A broken mail config (e.g. a missing RESEND_API_KEY) must never
        // fail the registration itself - the confirmation email is a nice
        // to have, not a condition of successfully signing up.
        try {
            Mail::to($registration->email)->queue(new RegistrationConfirmedMail($registration));
        } catch (\Throwable $e) {
            Log::error('Failed to queue registration confirmation email', ['registration_id' => $registration->id, 'error' => $e->getMessage()]);
        }

        return $registration;
    }

    /**
     * count()+1 isn't safe once guests can be deleted (see EventDetail's
     * guest-removal feature): deleting anything but the last registration
     * leaves a gap, so a later count()+1 can recompute a sequence number
     * that's still in use by a surviving registration and collide on the
     * qr_code unique constraint. Basing it on the highest sequence ever
     * issued for this event - regardless of what still exists - can only
     * go up, so it can never reissue a code that's already taken.
     */
    private function nextSequence(Event $event): int
    {
        $maxSeq = $event->registrations()
            ->pluck('qr_code')
            ->map(fn ($code) => (int) substr($code, -3))
            ->max();

        return ($maxSeq ?? 0) + 1;
    }

    /**
     * Capacity 0 means "not set" (matches the wizard's default) - treated
     * as unlimited rather than waitlisting everyone. Walk-ins bypass this
     * entirely (see createRegistration) since they're already in the room.
     */
    private function isEventFull(Event $event): bool
    {
        if ($event->capacity < 1) {
            return false;
        }

        return $event->registrations()->where('waitlisted', false)->count() >= $event->capacity;
    }

    /**
     * Organizer manually moves someone off the waitlist - e.g. after a
     * cancellation frees up a spot. No automatic promotion happens on its own.
     */
    public function promote(Registration $registration)
    {
        $registration->update(['waitlisted' => false]);

        return response()->json($registration);
    }

    /**
     * Bulk-add guests from a spreadsheet export. Expects a header row with
     * "name" and "email" columns (case-insensitive); any other header that
     * matches one of the event's custom_fields ids is mapped into that
     * field's answer. Rows with a missing name/email, an invalid email, or
     * an email already registered for this event are skipped and reported,
     * not fatal to the rest of the import.
     */
    public function importCsv(Request $request, Event $event)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);

        if (! $handle || ! $header) {
            return response()->json(['message' => 'Could not read that file.'], 422);
        }

        $columns = array_map(fn ($h) => strtolower(trim($h)), $header);
        $nameIdx = array_search('name', $columns, true);
        $emailIdx = array_search('email', $columns, true);

        if ($nameIdx === false || $emailIdx === false) {
            fclose($handle);

            return response()->json(['message' => 'The CSV needs "name" and "email" columns.'], 422);
        }

        $customFieldIds = collect($event->custom_fields ?? [])->pluck('id')->all();
        $existingEmails = $event->registrations()->pluck('email')->map(fn ($e) => strtolower($e))->all();

        $imported = 0;
        $errors = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $name = trim($row[$nameIdx] ?? '');
            $email = trim($row[$emailIdx] ?? '');

            if ($name === '' || $email === '') {
                $errors[] = "Row {$rowNum}: missing name or email";
                continue;
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNum}: invalid email \"{$email}\"";
                continue;
            }
            if (in_array(strtolower($email), $existingEmails, true)) {
                $errors[] = "Row {$rowNum}: {$email} is already registered";
                continue;
            }

            $customData = [];
            foreach ($customFieldIds as $id) {
                $idx = array_search(strtolower($id), $columns, true);
                if ($idx !== false && isset($row[$idx]) && $row[$idx] !== '') {
                    $customData[$id] = trim($row[$idx]);
                }
            }

            $this->createRegistration($event, [
                'name' => $name,
                'email' => $email,
                'custom_data' => $customData,
            ], $request, [
                'user_id' => null,
                'is_walk_in' => false,
            ]);

            $existingEmails[] = strtolower($email);
            $imported++;
        }

        fclose($handle);

        return response()->json([
            'imported' => $imported,
            'skipped' => count($errors),
            'errors' => $errors,
        ]);
    }

    public function indexForEvent(Event $event)
    {
        return response()->json($event->registrations()->orderByDesc('created_at')->get());
    }

    /**
     * Fellow attendees, for the "Connect" surface on the participant pass
     * page - discovery for the real connections feature (Connection model).
     * Gated by the requester themselves being registered for this event, so
     * it's not a way to enumerate an event's attendee list from the outside.
     */
    public function fellowAttendees(Request $request, Event $event)
    {
        $me = $request->user();
        abort_unless(
            Registration::where('event_id', $event->id)->where('user_id', $me->id)->exists(),
            403,
            'You must be registered for this event to see fellow attendees.'
        );

        $others = Registration::where('event_id', $event->id)
            ->where('user_id', '!=', $me->id)
            ->whereNotNull('user_id')
            ->with('user:id,name,avatar')
            ->get()
            ->unique('user_id')
            ->values();

        $connections = Connection::involving($me->id)->get();

        $attendees = $others->map(function (Registration $reg) use ($me, $connections) {
            $user = $reg->user;
            $conn = $connections->first(fn (Connection $c) => $c->requester_id === $user->id || $c->recipient_id === $user->id);

            $status = 'none';
            if ($conn && $conn->status === 'accepted') {
                $status = 'connected';
            } elseif ($conn && $conn->status === 'pending') {
                $status = $conn->requester_id === $me->id ? 'pendingSent' : 'pendingReceived';
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'connectionStatus' => $status,
                'connectionId' => $status === 'none' ? null : $conn->id,
            ];
        });

        return response()->json($attendees->values());
    }

    /**
     * Organizer edits a guest's details - name, email, custom answers,
     * certificate/attendance flags. Anything not sent is left unchanged.
     */
    public function update(Request $request, Registration $registration)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'custom_data' => ['sometimes', 'nullable', 'array'],
            'needs_certificate' => ['sometimes', 'boolean'],
            'attended' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('attended', $data)) {
            $data['check_in_time'] = $data['attended'] ? ($registration->check_in_time ?? now()) : null;
        }

        $registration->update($data);

        return response()->json($registration);
    }

    public function destroy(Registration $registration)
    {
        $registration->delete();

        return response()->json(['message' => 'Registration removed.']);
    }

    /**
     * "My tickets" - every event the logged-in account has registered for,
     * across all organizers. The account-based counterpart to lookup()
     * below, which exists for guests who don't have one.
     */
    public function mine(Request $request)
    {
        $registrations = Registration::with('event')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($registrations);
    }

    /**
     * Find-by-email pass lookup, matching findRegistrationsByEmail()
     * (App.jsx:98) - case-insensitive, trimmed. Mainly useful now for
     * organizer-added guests and walk-ins, who have no account to log into.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $registrations = Registration::with('event')
            ->whereRaw('lower(email) = ?', [strtolower(trim($data['email']))])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($registrations);
    }

    public function verifyPayment(Request $request, Registration $registration)
    {
        $data = $request->validate([
            'approved' => ['required', 'boolean'],
        ]);

        $registration->update([
            'payment_status' => $data['approved'] ? 'verified' : 'rejected',
        ]);

        return response()->json($registration);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NewRegistrationMail;
use App\Mail\PaymentRejectedMail;
use App\Mail\PaymentVerifiedMail;
use App\Mail\RegistrationConfirmedMail;
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
            'payment_ref' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Which of the event's payment_accounts entries they say they
            // paid into - a plain string id, not validated against the JSON
            // array's contents (same trust-the-frontend treatment
            // custom_fields ids already get elsewhere in this controller).
            'payment_account_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'payment_date' => ['sometimes', 'nullable', 'date'],
            'payment_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'payment_screenshot' => ['sometimes', 'nullable', 'image', 'max:5120'], // 5MB, matches the frontend's own limit
        ]);

        // Re-registering (double submit, revisiting the register page after
        // already signing up) used to create a second Registration row with
        // its own QR code - each independently scannable, silently inflating
        // attendance for what's really one person. Confirmed in live
        // testing: a single attendee's repeat registrations accounted for
        // 3 of 5 "unique" check-ins at one test event.
        $existing = Registration::where('event_id', $event->id)
            ->where('participant_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json($existing, 200);
        }

        $registration = $this->createRegistration($event, $data, $request, [
            'participant_id' => $request->user()->id,
            'is_walk_in' => false,
        ]);

        // Only for a real self-service signup - not walkIn()/importCsv(),
        // which the organizer already triggered themselves and doesn't need
        // telling about. A broken mail config must never fail the
        // registration itself, same reasoning as the confirmation email
        // inside createRegistration().
        if ($event->organizer?->email) {
            try {
                Mail::to($event->organizer->email)->queue(new NewRegistrationMail($registration));
            } catch (\Throwable $e) {
                Log::error('Failed to queue new-registration notification email', ['registration_id' => $registration->id, 'error' => $e->getMessage()]);
            }
        }

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
        ]);

        $registration = $this->createRegistration($event, $data, $request, [
            'participant_id' => null,
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
            'attended' => ['sometimes', 'boolean'],
        ]);

        $registration = $this->createRegistration($event, $data, $request, [
            'participant_id' => null,
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
            'waitlisted' => ! $isWalkIn && $this->isEventFull($event),
            'payment_status' => $paymentRef ? 'pending' : null,
            'payment_ref' => $paymentRef,
            'payment_account_id' => $data['payment_account_id'] ?? null,
            'payment_amount' => $data['payment_amount'] ?? null,
            'payment_date' => $data['payment_date'] ?? null,
            'payment_note' => $data['payment_note'] ?? null,
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
                'participant_id' => null,
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
     * Organizer edits a guest's details - name, email, custom answers,
     * attendance flag. Anything not sent is left unchanged.
     */
    public function update(Request $request, Registration $registration)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'custom_data' => ['sometimes', 'nullable', 'array'],
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
            ->where('participant_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($registrations);
    }

    /**
     * Find-by-email pass lookup, matching findRegistrationsByEmail()
     * (App.jsx:98) - case-insensitive, trimmed. Mainly useful now for
     * organizer-added guests and walk-ins, who have no account to log into.
     */
    /**
     * Email is the only real secret here (no login required), so this
     * returns the same restricted shape show() does - not the full model -
     * for the same reason: no custom_data, payment_ref, or payment
     * screenshot to anyone who happens to know or guess an email address.
     * pass_token IS included here (unlike show()'s response) - the frontend
     * needs it to build each result's /pass/:id?t= link.
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

        return response()->json($registrations->map(fn ($r) => $this->restrictedPayload($r)));
    }

    /**
     * Public, keyed by the registration's own id - which, unlike a token,
     * is a plain sequential integer and trivially enumerable (1, 2, 3, ...).
     * A registration created after the pass_token migration always has one,
     * and a request missing it (or presenting the wrong one) is treated as
     * not found - the URL itself is the credential, same idea as a signed
     * link. Older registrations from before that migration have a null
     * pass_token and are grandfathered to the old no-token behavior, since
     * there's no way to retroactively fix a link already sent by email.
     * Deliberately returns only what the Pass page actually renders, not
     * the full model: no email, custom_data, payment_ref, or the payment
     * screenshot URL. Lets the Pass page fetch live status (attended,
     * feedback_submitted, the event's current status) instead of only ever
     * showing whatever was true at the moment of registration, which is
     * all a plain /pass/:regId link previously had to go on.
     */
    public function show(Request $request, Registration $registration)
    {
        if ($registration->pass_token && $registration->pass_token !== $request->query('token')) {
            abort(404);
        }

        return response()->json($this->restrictedPayload($registration));
    }

    private function restrictedPayload(Registration $registration): array
    {
        // Same payment gate as QrCodeController::show() - the pass isn't
        // valid for check-in until the organizer verifies the payment, so
        // withholding the QR code itself (not just hiding it client-side)
        // means it can't leak through this endpoint before that happens.
        // Free/walk-in registrations never set payment_status, so this is
        // a no-op for them.
        $paymentBlocked = in_array($registration->payment_status, ['pending', 'rejected'], true);

        return [
            'id' => $registration->id,
            'name' => $registration->name,
            'qr_code' => $paymentBlocked ? null : $registration->qr_code,
            'pass_token' => $registration->pass_token,
            'payment_status' => $registration->payment_status,
            'event_id' => $registration->event_id,
            'attended' => $registration->attended,
            'feedback_submitted' => $registration->feedback_submitted,
            'event' => $registration->event()->select('id', 'title', 'slug', 'date', 'start_time', 'end_time', 'venue', 'status', 'feedback_enabled')->first(),
        ];
    }

    public function verifyPayment(Request $request, Registration $registration)
    {
        // Without this, the registrant who owns this registration could
        // authenticate as themselves and call this endpoint to unlock their
        // own gated QR pass without actually paying - which would make the
        // whole payment gate pointless. Same pattern (and the same "events
        // with no organization stay open" carve-out) as
        // EventController::authorizeOrgMember.
        $this->authorizeOrgMember($request, $registration->event);

        $data = $request->validate([
            'approved' => ['required', 'boolean'],
        ]);

        $registration->update([
            'payment_status' => $data['approved'] ? 'verified' : 'rejected',
        ]);

        // Same non-fatal queue-and-log pattern as createRegistration() above
        // - a broken mail config must never fail the actual verification.
        try {
            Mail::to($registration->email)->queue(
                $data['approved']
                    ? new PaymentVerifiedMail($registration)
                    : new PaymentRejectedMail($registration)
            );
        } catch (\Throwable $e) {
            Log::error('Failed to queue payment status email', ['registration_id' => $registration->id, 'error' => $e->getMessage()]);
        }

        return response()->json($registration);
    }

    /**
     * Same rule and the same "events with no organization stay open" carve-out
     * as EventController::authorizeOrgMember - kept as its own copy here
     * rather than a shared trait since that controller's version is private
     * and this is the only other place that currently needs it.
     */
    private function authorizeOrgMember(Request $request, Event $event): void
    {
        abort_if(
            ! $request->user()->isAdmin()
                && $event->organization_id !== null
                && ! $request->user()->organizations()->where('organizations.id', $event->organization_id)->exists(),
            403,
            'Only a member of this event\'s organization can do that.'
        );
    }
}

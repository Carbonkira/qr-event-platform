<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Scan a QR code and mark attendance. Response shape mirrors the mock's
     * markAttendance() exactly (App.jsx:107-113): {success, type, registration}.
     *
     * A scanner can only check in people for events its own account manages
     * (see Organizer::canManageEvent) - previously any approved organizer
     * could check anyone in to any organization's event by presenting the
     * right code. A code for an event they don't manage answers exactly like
     * an unknown code, so scanning can't be used to find out which codes exist.
     */
    public function scan(Request $request)
    {
        $data = $request->validate([
            'qr_code' => ['required', 'string'],
        ]);

        // Staff sometimes type a code in by hand at the door: stray spaces
        // and lowercase shouldn't turn a real code into "not found". Codes
        // are always stored uppercase.
        $code = trim($data['qr_code']);
        $registration = Registration::with('event')
            ->where(fn ($q) => $q->where('qr_code', $code)->orWhere('qr_code', strtoupper($code)))
            ->first();

        if (! $registration || ! $request->user()->canManageEvent($registration->event)) {
            return response()->json([
                'success' => false,
                'type' => 'not_found',
            ]);
        }

        if ($registration->attended) {
            return response()->json([
                'success' => false,
                'type' => 'duplicate',
                'registration' => $registration,
            ]);
        }

        $registration->attended = true;
        $registration->check_in_time = now();
        $registration->save();

        return response()->json([
            'success' => true,
            'registration' => $registration,
        ]);
    }
}

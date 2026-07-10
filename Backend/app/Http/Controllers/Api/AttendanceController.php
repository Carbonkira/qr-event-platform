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
     */
    public function scan(Request $request)
    {
        $data = $request->validate([
            'qr_code' => ['required', 'string'],
        ]);

        $registration = Registration::where('qr_code', $data['qr_code'])->first();

        if (! $registration) {
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

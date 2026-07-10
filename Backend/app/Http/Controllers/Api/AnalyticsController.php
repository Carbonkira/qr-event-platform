<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\Registration;

class AnalyticsController extends Controller
{
    /**
     * Mirrors the mock's getAnalytics() shape exactly (App.jsx:127-131),
     * including its camelCase keys, so the ported frontend can consume it
     * without remapping.
     */
    public function index()
    {
        $totalRegistrations = Registration::count();
        $totalAttended = Registration::where('attended', true)->count();
        $totalFeedback = Feedback::count();

        $avgSatisfaction = 0;
        if ($totalFeedback > 0) {
            // Average of each feedback row's own (q1+q2+q3+q4+q5)/5, then
            // averaged across all rows - matches the mock's reduce() exactly.
            $sumOfPerRowAverages = Feedback::query()
                ->selectRaw('SUM((q1 + q2 + q3 + q4 + q5) / 5.0) as total')
                ->value('total');
            $avgSatisfaction = $sumOfPerRowAverages / $totalFeedback;
        }

        return response()->json([
            'totalEvents' => Event::where('status', 'approved')->count(),
            'totalRegistrations' => $totalRegistrations,
            'totalAttended' => $totalAttended,
            'attendanceRate' => $totalRegistrations > 0
                ? (string) round(($totalAttended / $totalRegistrations) * 100)
                : '0',
            'totalFeedback' => $totalFeedback,
            'feedbackRate' => $totalAttended > 0
                ? (string) round(($totalFeedback / $totalAttended) * 100)
                : '0',
            'avgSatisfaction' => number_format($avgSatisfaction, 1),
            'pendingApprovals' => Event::where('status', 'pending')->count(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\Registration;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Mirrors the mock's getAnalytics() shape exactly (App.jsx:127-131),
     * including its camelCase keys, so the ported frontend can consume it
     * without remapping. Scoped to events belonging to any organization the
     * requester is a member of, unless they're an admin - previously this
     * scoped to "events I personally created," which under-counted a
     * co-officer's own club's events once organizations became real teams.
     */
    public function index(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();
        $orgIds = $request->user()->organizations()->pluck('organizations.id');
        $ownedEventIds = Event::where(fn ($q) => $q->whereIn('organization_id', $orgIds)->orWhereNull('organization_id'))->pluck('id');

        $registrations = Registration::query();
        $feedback = Feedback::query();
        $events = Event::query();

        if (! $isAdmin) {
            $registrations->whereIn('event_id', $ownedEventIds);
            $feedback->whereIn('event_id', $ownedEventIds);
            $events->whereIn('id', $ownedEventIds);
        }

        $totalRegistrations = (clone $registrations)->count();
        $totalAttended = (clone $registrations)->where('attended', true)->count();
        $totalFeedback = (clone $feedback)->count();

        $avgSatisfaction = 0;
        if ($totalFeedback > 0) {
            // Average of each feedback row's own (q1+q2+q3+q4+q5)/5, then
            // averaged across all rows - matches the mock's reduce() exactly.
            $sumOfPerRowAverages = (clone $feedback)
                ->selectRaw('SUM((q1 + q2 + q3 + q4 + q5) / 5.0) as total')
                ->value('total');
            $avgSatisfaction = $sumOfPerRowAverages / $totalFeedback;
        }

        return response()->json([
            'totalEvents' => (clone $events)->where('status', 'approved')->count(),
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
            // Only ever meaningful for an admin - EventController::approve()/
            // reject() are strictly admin-gated with no "you created this
            // org-less event" exception, so a non-admin seeing this banner
            // could only ever click through to a permission-denied page.
            // Confirmed live by two independent testers.
            'pendingApprovals' => $isAdmin ? Event::where('status', 'pending')->count() : 0,
        ]);
    }
}

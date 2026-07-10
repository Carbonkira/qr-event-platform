<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeedbackController extends Controller
{
    /**
     * Submit feedback for an event. Ports the mock's addFeedback() exactly
     * (App.jsx:115-122):
     *  - badge: "🏆 Super Reviewer" if the participant already has 2+ prior
     *    feedback submissions for *this event*, "⭐ Top Reviewer" if 1+,
     *    else null. (The mock counts total feedback rows for the event, not
     *    per-participant - ported literally as-is.)
     *  - is_highlighted: true if avg(q1..q5) >= 4.5, OR an explicit
     *    "is_important" flag was passed.
     *
     * The 5 rating columns (q1-q5) always correspond to the first 5 entries
     * of event.feedbackQuestionsOrDefault() - an organizer can rename them,
     * but not remove them (they're fixed columns). Anything past those 5 is
     * an organizer-added custom question, answered into custom_answers.
     */
    public function store(Request $request, Event $event)
    {
        $data = $request->validate([
            'registration_id' => ['required', 'integer', 'exists:registrations,id'],
            'q1' => ['required', 'integer', 'between:1,5'],
            'q2' => ['required', 'integer', 'between:1,5'],
            'q3' => ['required', 'integer', 'between:1,5'],
            'q4' => ['required', 'integer', 'between:1,5'],
            'q5' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string'],
            'is_important' => ['sometimes', 'boolean'],
            'custom_answers' => ['sometimes', 'nullable', 'array'],
        ]);

        $registration = Registration::where('id', $data['registration_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        $extraQuestions = array_slice($event->feedbackQuestionsOrDefault(), 5);
        $customAnswers = $data['custom_answers'] ?? [];
        foreach ($extraQuestions as $question) {
            if (($question['required'] ?? false) && trim((string) ($customAnswers[$question['id']] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "custom_answers.{$question['id']}" => ["\"{$question['label']}\" is required."],
                ]);
            }
        }

        $countForEvent = Feedback::where('event_id', $event->id)->count();
        $badge = $countForEvent >= 2 ? '🏆 Super Reviewer' : ($countForEvent >= 1 ? '⭐ Top Reviewer' : null);

        $average = ($data['q1'] + $data['q2'] + $data['q3'] + $data['q4'] + $data['q5']) / 5;
        $isHighlighted = $average >= 4.5 || ($data['is_important'] ?? false);

        $feedback = Feedback::create([
            'registration_id' => $registration->id,
            'event_id' => $event->id,
            'q1' => $data['q1'],
            'q2' => $data['q2'],
            'q3' => $data['q3'],
            'q4' => $data['q4'],
            'q5' => $data['q5'],
            'comment' => $data['comment'] ?? null,
            'custom_answers' => $customAnswers ?: null,
            'is_highlighted' => $isHighlighted,
            'badge' => $badge,
        ]);

        $registration->update(['feedback_submitted' => true]);

        return response()->json($feedback, 201);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['sometimes', 'nullable', 'integer', 'exists:events,id'],
        ]);

        $query = Feedback::with('registration')->orderByDesc('created_at');

        if (! empty($data['event_id'])) {
            $query->where('event_id', $data['event_id']);
        }

        return response()->json($query->get());
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Feedback;
use App\Services\Gemini;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FeedbackSummaryController extends Controller
{
    /**
     * AI-generated synthesis of an event's feedback (plan §4).
     *
     * - Caches on events.ai_summary / ai_summary_generated_at; skips
     *   regeneration unless ?refresh=true is passed or nothing is cached yet.
     * - If there are zero responses at all (even rating-only, no comment),
     *   returns {summary: null, ...} WITHOUT calling the Gemini API.
     * - The API key is read only from config('services.gemini.key')
     *   (server-side .env) and is never included in the response.
     */
    public function generate(Request $request, Event $event)
    {
        // Matches EventController::authorizeOrgMember - any member of the
        // event's organization can view its summary (null organization_id =
        // legacy event, stays open to anyone; admins bypass entirely).
        abort_if(
            ! $request->user()->isAdmin()
                && $event->organization_id !== null
                && ! $request->user()->organizations()->where('organizations.id', $event->organization_id)->exists(),
            403,
            'Only a member of this event\'s organization can view its feedback summary.'
        );

        $refresh = $request->boolean('refresh');

        if (! $refresh && $event->ai_summary) {
            return response()->json([
                'summary' => $event->ai_summary,
                'generated_at' => $event->ai_summary_generated_at,
                'cached' => true,
            ]);
        }

        $feedback = Feedback::where('event_id', $event->id)->get();

        if ($feedback->isEmpty()) {
            return response()->json([
                'summary' => null,
                'message' => 'Not enough feedback yet to generate a summary.',
                'cached' => false,
            ]);
        }

        abort_unless(Gemini::isConfigured(), 503, 'AI summaries aren\'t configured on this server yet - set GEMINI_API_KEY.');

        $prompt = $this->buildPrompt($event, $feedback);

        $event->ai_summary = Gemini::generate($prompt, 3072);
        $event->ai_summary_generated_at = now();
        $event->save();

        return response()->json([
            'summary' => $event->ai_summary,
            'generated_at' => $event->ai_summary_generated_at,
            'cached' => false,
        ]);
    }

    /**
     * Renders every response as a labeled row (real question labels, not
     * "q1"/"q2") plus per-question stats, then hands the whole dataset to
     * the organizer-facing analysis brief below. The brief's structure is
     * closer to a survey-analytics report than the old 3-5 sentence recap -
     * sentiment distribution, recurring themes, and concrete actions, in
     * that priority order, plus an overview/breakdown/metrics pass beneath.
     */
    private function buildPrompt(Event $event, Collection $feedback): string
    {
        $questions = $event->feedbackQuestionsOrDefault();
        $coreQuestions = array_slice($questions, 0, 5);
        $extraQuestions = array_slice($questions, 5);

        $rows = $feedback->values()->map(function (Feedback $f, int $i) use ($coreQuestions, $extraQuestions) {
            $ratings = [];
            foreach ($coreQuestions as $idx => $q) {
                $ratings[] = "{$q['label']}={$f->{'q'.($idx + 1)}}";
            }
            foreach ($extraQuestions as $q) {
                $answer = $f->custom_answers[$q['id']] ?? null;
                if ($answer !== null && $answer !== '') {
                    $ratings[] = "{$q['label']}=".($q['type'] === 'rating' ? $answer : '"'.$answer.'"');
                }
            }
            $comment = $f->comment ? '"'.$f->comment.'"' : '(none)';
            $flag = $f->is_highlighted ? ', flagged important by attendee' : '';

            return sprintf('%d. [%s]%s Comment: %s', $i + 1, implode(', ', $ratings), $flag, $comment);
        })->implode("\n");

        $total = $feedback->count();
        $withComment = $feedback->filter(fn (Feedback $f) => filled($f->comment))->count();
        $highlighted = $feedback->where('is_highlighted', true)->count();

        $coreAverages = collect($coreQuestions)->map(function ($q, $idx) use ($feedback) {
            $avg = $feedback->avg('q'.($idx + 1));

            return sprintf('%s: avg %.1f/5', $q['label'], $avg);
        })->implode(', ');

        return <<<PROMPT
        I have collected responses from an event feedback form for "{$event->title}". Analyze the data below and produce a structured summary for the event organizing and planning team, prioritizing the following in order of importance:

        1. Overall sentiment — classify responses as positive, neutral, or negative, and show the distribution.
        2. Recurring themes and common feedback topics — identify what attendees said most often, across both the free-text comments and the rating questions.
        3. Actionable insights and recommendations — concrete suggestions the planning team can act on to improve future events.

        Also cover:
        - Overview: total number of responses ({$total} total, {$withComment} left a comment, {$highlighted} flagged their feedback as important) and any notable completion patterns.
        - Categorical breakdown: distribution of answers across the rating questions below, using their real labels (not "q1"/"q2" etc).
        - Key metrics: average scores per question, highest/lowest-rated aspects, and any standout quantitative signals.

        For the free-text comments, identify recurring themes and quote specific responses where they're representative or particularly notable. If any response is ambiguous, inconsistent, or missing data, flag it rather than assuming. Structure the output for easy skimming — use headers, bullet points, and short tables where they add clarity. This is a written report, not a chat reply, so skip any conversational preamble.

        Question labels and averages across all {$total} responses: {$coreAverages}.

        Individual responses (one per line: each rated question, whether this response was flagged important, then their comment):
        {$rows}
        PROMPT;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedbackSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeEventWithRegistration(): Event
    {
        $event = Event::create([
            'title' => 'Test Event', 'status' => 'approved', 'slug' => 'test-event-'.uniqid(),
            'type' => 'Meetup', 'venue' => 'Venue', 'date' => '2026-08-01',
            'start_time' => '10:00', 'end_time' => '12:00', 'capacity' => 50,
        ]);
        $event->registrations()->create(['name' => 'Attendee', 'email' => 'attendee@example.com', 'qr_code' => 'QR-TEST-001']);

        return $event;
    }

    private function actingAsOrganizer(): User
    {
        $user = User::create(['name' => 'Org', 'email' => 'org@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_feedback_summary_requires_authentication(): void
    {
        $event = $this->makeEventWithRegistration();

        $this->postJson("/api/events/{$event->id}/feedback-summary")->assertUnauthorized();
    }

    public function test_feedback_summary_returns_null_when_there_is_no_feedback_yet(): void
    {
        $event = $this->makeEventWithRegistration();
        $this->actingAsOrganizer();

        $this->postJson("/api/events/{$event->id}/feedback-summary")
            ->assertOk()
            ->assertJsonPath('summary', null);
    }

    public function test_a_rating_only_response_with_no_comment_still_counts_as_feedback(): void
    {
        // Regression: this used to check for non-empty *comments* specifically,
        // so an event where everyone rated but nobody typed a comment was
        // wrongly reported as having no feedback at all.
        $event = $this->makeEventWithRegistration();
        $registration = $event->registrations()->first();
        $event->feedback()->create([
            'registration_id' => $registration->id,
            'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'q5' => 5,
        ]);
        $this->actingAsOrganizer();
        config(['services.gemini.key' => null]);

        // Reaches the "generate" path (503 for missing key) rather than the
        // "no feedback" path (200 with summary: null) - proving it was counted.
        $this->postJson("/api/events/{$event->id}/feedback-summary")->assertStatus(503);
    }

    public function test_feedback_summary_fails_clearly_when_no_gemini_key_is_configured(): void
    {
        $event = $this->makeEventWithRegistration();
        $registration = $event->registrations()->first();
        $event->feedback()->create([
            'registration_id' => $registration->id,
            'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'q5' => 5, 'comment' => 'Great!',
        ]);
        $this->actingAsOrganizer();
        config(['services.gemini.key' => null]);

        $this->postJson("/api/events/{$event->id}/feedback-summary")->assertStatus(503);
    }

    public function test_feedback_summary_calls_gemini_and_caches_the_result(): void
    {
        $event = $this->makeEventWithRegistration();
        $registration = $event->registrations()->first();
        $event->feedback()->create([
            'registration_id' => $registration->id,
            'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'q5' => 5, 'comment' => 'Great check-in flow!',
        ]);
        $this->actingAsOrganizer();
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Attendees loved the check-in flow.']]]],
                ],
            ]),
        ]);

        $this->postJson("/api/events/{$event->id}/feedback-summary")
            ->assertOk()
            ->assertJsonPath('summary', 'Attendees loved the check-in flow.')
            ->assertJsonPath('cached', false);

        $this->assertSame('Attendees loved the check-in flow.', $event->fresh()->ai_summary);

        // A second call without ?refresh should hit the cache, not Gemini again.
        Http::fake(); // any further request would fail this assertion
        $this->postJson("/api/events/{$event->id}/feedback-summary")
            ->assertOk()
            ->assertJsonPath('cached', true);
    }
}

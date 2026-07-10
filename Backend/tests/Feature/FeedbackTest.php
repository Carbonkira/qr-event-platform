<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeRegistration(array $eventOverrides = []): Registration
    {
        $event = Event::create(array_merge([
            'title' => 'Test Event', 'status' => 'approved', 'slug' => 'test-event-'.uniqid(),
            'type' => 'Meetup', 'venue' => 'Venue', 'date' => '2026-08-01',
            'start_time' => '10:00', 'end_time' => '12:00', 'capacity' => 50,
        ], $eventOverrides));

        return $event->registrations()->create(['name' => 'Attendee', 'email' => 'attendee@example.com', 'qr_code' => 'QR-TEST-001']);
    }

    public function test_all_five_core_ratings_are_required(): void
    {
        $registration = $this->makeRegistration();

        $this->postJson("/api/events/{$registration->event_id}/feedback", [
            'registrationId' => $registration->id,
            'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5,
            // q5 missing
        ])->assertStatus(422)->assertJsonValidationErrors(['q5']);
    }

    public function test_a_required_custom_question_must_be_answered(): void
    {
        $registration = $this->makeRegistration(['feedback_questions' => [
            ['id' => 'q1', 'label' => 'Check-in', 'type' => 'rating', 'required' => true],
            ['id' => 'q2', 'label' => 'Organization', 'type' => 'rating', 'required' => true],
            ['id' => 'q3', 'label' => 'Content', 'type' => 'rating', 'required' => true],
            ['id' => 'q4', 'label' => 'Venue', 'type' => 'rating', 'required' => true],
            ['id' => 'q5', 'label' => 'Overall', 'type' => 'rating', 'required' => true],
            ['id' => 'fq1', 'label' => 'Would you recommend this?', 'type' => 'text', 'required' => true],
        ]]);

        $this->postJson("/api/events/{$registration->event_id}/feedback", [
            'registrationId' => $registration->id,
            'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'q5' => 5,
        ])->assertStatus(422);

        $this->postJson("/api/events/{$registration->event_id}/feedback", [
            'registrationId' => $registration->id,
            'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'q5' => 5,
            'customAnswers' => ['fq1' => 'Yes!'],
        ])->assertCreated();
    }

    public function test_a_perfect_average_is_highlighted_and_the_second_submission_earns_a_badge(): void
    {
        $reg1 = $this->makeRegistration();
        $event = Event::find($reg1->event_id);
        $reg2 = $event->registrations()->create(['name' => 'Second', 'email' => 'second@example.com', 'qr_code' => 'QR-TEST-002']);

        $first = $this->postJson("/api/events/{$event->id}/feedback", [
            'registrationId' => $reg1->id, 'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'q5' => 5,
        ])->assertCreated();
        $this->assertTrue($first->json('isHighlighted'));
        $this->assertNull($first->json('badge'));

        $second = $this->postJson("/api/events/{$event->id}/feedback", [
            'registrationId' => $reg2->id, 'q1' => 3, 'q2' => 3, 'q3' => 3, 'q4' => 3, 'q5' => 3,
        ])->assertCreated();
        $this->assertFalse($second->json('isHighlighted'));
        $this->assertSame('⭐ Top Reviewer', $second->json('badge'));
    }
}

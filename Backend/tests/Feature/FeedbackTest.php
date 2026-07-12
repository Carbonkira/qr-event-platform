<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    public function test_feedback_index_only_shows_feedback_for_events_the_organizer_owns(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]);
        $stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@example.com', 'password' => bcrypt('password123')]);
        $ownerOrg = Organization::create(['name' => "Owner's Org", 'slug' => 'owners-org-'.uniqid()]);
        $ownerOrg->members()->attach($owner->id, ['role' => 'owner']);
        $strangerOrg = Organization::create(['name' => "Stranger's Org", 'slug' => 'strangers-org-'.uniqid()]);
        $strangerOrg->members()->attach($stranger->id, ['role' => 'owner']);

        $ownedEvent = Event::create(['title' => 'Owned', 'status' => 'approved', 'slug' => 'owned-'.uniqid(), 'user_id' => $owner->id, 'organization_id' => $ownerOrg->id]);
        $ownedReg = $ownedEvent->registrations()->create(['name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A']);
        $ownedEvent->feedback()->create(['registration_id' => $ownedReg->id, 'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'q5' => 5]);

        $othersEvent = Event::create(['title' => 'Others', 'status' => 'approved', 'slug' => 'others-'.uniqid(), 'user_id' => $stranger->id, 'organization_id' => $strangerOrg->id]);
        $othersReg = $othersEvent->registrations()->create(['name' => 'B', 'email' => 'b@example.com', 'qr_code' => 'QR-B']);
        $othersEvent->feedback()->create(['registration_id' => $othersReg->id, 'q1' => 3, 'q2' => 3, 'q3' => 3, 'q4' => 3, 'q5' => 3]);

        Sanctum::actingAs($owner);
        $response = $this->getJson('/api/feedback')->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame($ownedEvent->id, $response->json('0.eventId'));
    }

    public function test_feedback_index_shows_everything_to_an_admin(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password123')]);
        $admin->forceFill(['role' => 'admin'])->save();
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]);

        $event = Event::create(['title' => 'Owned', 'status' => 'approved', 'slug' => 'owned-'.uniqid(), 'user_id' => $owner->id]);
        $reg = $event->registrations()->create(['name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A']);
        $event->feedback()->create(['registration_id' => $reg->id, 'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'q5' => 5]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/feedback')->assertOk()->assertJsonCount(1);
    }
}

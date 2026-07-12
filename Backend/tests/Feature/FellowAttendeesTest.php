<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FellowAttendeesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email): User
    {
        return User::create(['name' => ucfirst(explode('@', $email)[0]), 'email' => $email, 'password' => bcrypt('password123')]);
    }

    private function makeEvent(): Event
    {
        $owner = $this->makeUser('owner@example.com');

        return Event::create(['title' => 'Meetup', 'status' => 'approved', 'slug' => 'meetup-'.uniqid(), 'user_id' => $owner->id]);
    }

    public function test_a_registrant_sees_other_registrants_but_not_themselves(): void
    {
        $event = $this->makeEvent();
        $me = $this->makeUser('me@example.com');
        $other = $this->makeUser('other@example.com');
        Registration::create(['event_id' => $event->id, 'user_id' => $me->id, 'name' => 'Me', 'email' => 'me@example.com', 'qr_code' => 'QR1']);
        Registration::create(['event_id' => $event->id, 'user_id' => $other->id, 'name' => 'Other', 'email' => 'other@example.com', 'qr_code' => 'QR2']);

        Sanctum::actingAs($me);
        $response = $this->getJson("/api/events/{$event->id}/attendees")->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame('Other', $response->json('0.name'));
        $this->assertSame('none', $response->json('0.connectionStatus'));
    }

    public function test_someone_not_registered_for_the_event_cannot_see_attendees(): void
    {
        $event = $this->makeEvent();
        $stranger = $this->makeUser('stranger@example.com');

        Sanctum::actingAs($stranger);
        $this->getJson("/api/events/{$event->id}/attendees")->assertForbidden();
    }

    public function test_connection_status_reflects_existing_connections(): void
    {
        $event = $this->makeEvent();
        $me = $this->makeUser('me@example.com');
        $connected = $this->makeUser('connected@example.com');
        $pendingSent = $this->makeUser('pending-sent@example.com');
        $pendingReceived = $this->makeUser('pending-received@example.com');

        foreach ([$connected, $pendingSent, $pendingReceived] as $i => $u) {
            Registration::create(['event_id' => $event->id, 'user_id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'qr_code' => 'QR-U'.$i]);
        }
        Registration::create(['event_id' => $event->id, 'user_id' => $me->id, 'name' => 'Me', 'email' => 'me@example.com', 'qr_code' => 'QR-ME']);

        Connection::create(['requester_id' => $me->id, 'recipient_id' => $connected->id, 'status' => 'accepted']);
        Connection::create(['requester_id' => $me->id, 'recipient_id' => $pendingSent->id, 'status' => 'pending']);
        Connection::create(['requester_id' => $pendingReceived->id, 'recipient_id' => $me->id, 'status' => 'pending']);

        Sanctum::actingAs($me);
        $response = $this->getJson("/api/events/{$event->id}/attendees")->assertOk();
        $byName = collect($response->json())->keyBy('name');

        $this->assertSame('connected', $byName[$connected->name]['connectionStatus']);
        $this->assertSame('pendingSent', $byName[$pendingSent->name]['connectionStatus']);
        $this->assertSame('pendingReceived', $byName[$pendingReceived->name]['connectionStatus']);
    }
}

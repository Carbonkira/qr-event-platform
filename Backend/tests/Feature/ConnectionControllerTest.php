<?php

namespace Tests\Feature;

use App\Mail\ConnectionRequestMail;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConnectionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email): User
    {
        return User::create(['name' => ucfirst(explode('@', $email)[0]), 'email' => $email, 'password' => bcrypt('password123')]);
    }

    public function test_a_user_can_send_a_connection_request(): void
    {
        $me = $this->makeUser('me@example.com');
        $them = $this->makeUser('them@example.com');

        Sanctum::actingAs($me);
        $this->postJson('/api/connections', ['recipient_id' => $them->id])->assertCreated();

        $this->assertDatabaseHas('connections', ['requester_id' => $me->id, 'recipient_id' => $them->id, 'status' => 'pending']);
    }

    public function test_sending_a_connection_request_emails_the_recipient(): void
    {
        Mail::fake();
        $me = $this->makeUser('me@example.com');
        $them = $this->makeUser('them@example.com');

        Sanctum::actingAs($me);
        $this->postJson('/api/connections', ['recipient_id' => $them->id])->assertCreated();

        Mail::assertQueued(ConnectionRequestMail::class, fn ($mail) => $mail->hasTo($them->email));
    }

    public function test_cannot_send_a_connection_request_to_yourself(): void
    {
        $me = $this->makeUser('me@example.com');

        Sanctum::actingAs($me);
        $this->postJson('/api/connections', ['recipient_id' => $me->id])->assertUnprocessable();
    }

    public function test_cannot_send_a_duplicate_request_while_one_is_pending(): void
    {
        $me = $this->makeUser('me@example.com');
        $them = $this->makeUser('them@example.com');
        Connection::create(['requester_id' => $me->id, 'recipient_id' => $them->id, 'status' => 'pending']);

        Sanctum::actingAs($me);
        $this->postJson('/api/connections', ['recipient_id' => $them->id])->assertUnprocessable();

        // Also blocked from the other direction - same pair, either order.
        Sanctum::actingAs($them);
        $this->postJson('/api/connections', ['recipient_id' => $me->id])->assertUnprocessable();
    }

    public function test_cannot_request_a_connection_that_already_exists(): void
    {
        $me = $this->makeUser('me@example.com');
        $them = $this->makeUser('them@example.com');
        Connection::create(['requester_id' => $me->id, 'recipient_id' => $them->id, 'status' => 'accepted']);

        Sanctum::actingAs($me);
        $this->postJson('/api/connections', ['recipient_id' => $them->id])->assertUnprocessable();
    }

    public function test_a_declined_request_can_be_re_sent(): void
    {
        $me = $this->makeUser('me@example.com');
        $them = $this->makeUser('them@example.com');
        $original = Connection::create(['requester_id' => $them->id, 'recipient_id' => $me->id, 'status' => 'declined']);

        Sanctum::actingAs($me);
        $this->postJson('/api/connections', ['recipient_id' => $them->id])->assertCreated();

        $this->assertSame('pending', $original->fresh()->status);
        $this->assertSame($me->id, $original->fresh()->requester_id);
    }

    public function test_only_the_recipient_can_accept_or_decline_a_request(): void
    {
        $requester = $this->makeUser('requester@example.com');
        $recipient = $this->makeUser('recipient@example.com');
        $connection = Connection::create(['requester_id' => $requester->id, 'recipient_id' => $recipient->id, 'status' => 'pending']);

        Sanctum::actingAs($requester);
        $this->postJson("/api/connections/{$connection->id}/accept")->assertForbidden();

        Sanctum::actingAs($recipient);
        $this->postJson("/api/connections/{$connection->id}/accept")->assertOk();
        $this->assertSame('accepted', $connection->fresh()->status);
    }

    public function test_declining_marks_the_request_declined_not_deleted(): void
    {
        $requester = $this->makeUser('requester@example.com');
        $recipient = $this->makeUser('recipient@example.com');
        $connection = Connection::create(['requester_id' => $requester->id, 'recipient_id' => $recipient->id, 'status' => 'pending']);

        Sanctum::actingAs($recipient);
        $this->postJson("/api/connections/{$connection->id}/decline")->assertOk();
        $this->assertSame('declined', $connection->fresh()->status);
    }

    public function test_either_party_can_remove_an_accepted_connection(): void
    {
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');
        $connection = Connection::create(['requester_id' => $a->id, 'recipient_id' => $b->id, 'status' => 'accepted']);

        Sanctum::actingAs($b);
        $this->deleteJson("/api/connections/{$connection->id}")->assertOk();
        $this->assertDatabaseMissing('connections', ['id' => $connection->id]);
    }

    public function test_a_stranger_cannot_remove_someone_elses_connection(): void
    {
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');
        $stranger = $this->makeUser('stranger@example.com');
        $connection = Connection::create(['requester_id' => $a->id, 'recipient_id' => $b->id, 'status' => 'accepted']);

        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/connections/{$connection->id}")->assertForbidden();
    }

    public function test_index_splits_connections_into_accepted_incoming_and_outgoing(): void
    {
        $me = $this->makeUser('me@example.com');
        $friend = $this->makeUser('friend@example.com');
        $incoming = $this->makeUser('incoming@example.com');
        $outgoing = $this->makeUser('outgoing@example.com');

        Connection::create(['requester_id' => $me->id, 'recipient_id' => $friend->id, 'status' => 'accepted']);
        Connection::create(['requester_id' => $incoming->id, 'recipient_id' => $me->id, 'status' => 'pending']);
        Connection::create(['requester_id' => $me->id, 'recipient_id' => $outgoing->id, 'status' => 'pending']);

        Sanctum::actingAs($me);
        $response = $this->getJson('/api/connections')->assertOk();

        // Only name/avatar are exposed here - no email, per the connections
        // plan ("no contact info is shared until a request is accepted").
        $this->assertCount(1, $response->json('accepted'));
        $this->assertSame($friend->name, $response->json('accepted.0.user.name'));
        $this->assertCount(1, $response->json('incoming'));
        $this->assertSame($incoming->name, $response->json('incoming.0.user.name'));
        $this->assertCount(1, $response->json('outgoing'));
        $this->assertSame($outgoing->name, $response->json('outgoing.0.user.name'));
    }
}

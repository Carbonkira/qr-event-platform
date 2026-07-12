<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscussionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'user@example.com'): User
    {
        return User::create(['name' => 'Test User', 'email' => $email, 'password' => bcrypt('password123')]);
    }

    private function makeOrg(User $owner): Organization
    {
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid()]);
        $org->members()->attach($owner->id, ['role' => 'owner']);

        return $org;
    }

    public function test_a_member_can_create_and_list_threads(): void
    {
        $owner = $this->makeUser();
        $org = $this->makeOrg($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/orgs/{$org->id}/discussion", ['title' => 'Setup help?', 'body' => 'Anyone free Saturday?'])->assertCreated();

        $response = $this->getJson("/api/orgs/{$org->id}/discussion")->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('Setup help?', $response->json('0.title'));
    }

    public function test_a_registrant_who_is_not_a_member_can_view_and_post(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrg($owner);
        $event = Event::create(['title' => 'Workshop', 'status' => 'approved', 'slug' => 'workshop', 'user_id' => $owner->id, 'organization_id' => $org->id]);
        $registrant = $this->makeUser('registrant@example.com');
        Registration::create(['event_id' => $event->id, 'user_id' => $registrant->id, 'name' => 'Registrant', 'email' => 'registrant@example.com', 'qr_code' => 'QR1']);

        Sanctum::actingAs($registrant);
        $this->getJson("/api/orgs/{$org->id}/discussion")->assertOk();
        $this->postJson("/api/orgs/{$org->id}/discussion", ['title' => 'Question', 'body' => 'What should I bring?'])->assertCreated();
    }

    public function test_a_stranger_with_no_membership_or_registration_is_forbidden(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrg($owner);
        $stranger = $this->makeUser('stranger@example.com');

        Sanctum::actingAs($stranger);
        $this->getJson("/api/orgs/{$org->id}/discussion")->assertForbidden();
        $this->postJson("/api/orgs/{$org->id}/discussion", ['title' => 'Hi', 'body' => 'Let me in?'])->assertForbidden();
    }

    public function test_a_member_of_a_different_organization_cannot_access_this_ones_discussion(): void
    {
        $ownerA = $this->makeUser('a@example.com');
        $orgA = $this->makeOrg($ownerA);
        $ownerB = $this->makeUser('b@example.com');
        $this->makeOrg($ownerB);

        Sanctum::actingAs($ownerB);
        $this->getJson("/api/orgs/{$orgA->id}/discussion")->assertForbidden();
    }

    public function test_viewing_a_thread_includes_its_replies_and_a_member_can_reply(): void
    {
        $owner = $this->makeUser();
        $org = $this->makeOrg($owner);
        Sanctum::actingAs($owner);
        $thread = $this->postJson("/api/orgs/{$org->id}/discussion", ['title' => 'Setup help?', 'body' => 'Anyone free?'])->json();

        $this->postJson("/api/discussion/threads/{$thread['id']}/replies", ['body' => "I'm in!"])->assertCreated();

        $response = $this->getJson("/api/discussion/threads/{$thread['id']}")->assertOk();
        $this->assertCount(1, $response->json('replies'));
        $this->assertSame("I'm in!", $response->json('replies.0.body'));
    }

    public function test_a_stranger_cannot_view_or_reply_to_a_thread(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrg($owner);
        Sanctum::actingAs($owner);
        $thread = $this->postJson("/api/orgs/{$org->id}/discussion", ['title' => 'Setup help?', 'body' => 'Anyone free?'])->json();

        $stranger = $this->makeUser('stranger@example.com');
        Sanctum::actingAs($stranger);
        $this->getJson("/api/discussion/threads/{$thread['id']}")->assertForbidden();
        $this->postJson("/api/discussion/threads/{$thread['id']}/replies", ['body' => 'Let me in'])->assertForbidden();
    }

    public function test_title_and_body_are_required_to_create_a_thread(): void
    {
        $owner = $this->makeUser();
        $org = $this->makeOrg($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/orgs/{$org->id}/discussion", [])->assertUnprocessable();
    }
}

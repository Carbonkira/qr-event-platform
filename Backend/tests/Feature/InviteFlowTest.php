<?php

namespace Tests\Feature;

use App\Mail\OrganizationInviteMail;
use App\Mail\OrganizationMemberJoinedMail;
use App\Models\Organization;
use App\Models\OrganizationInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InviteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'user@example.com'): User
    {
        return User::create(['name' => 'Test User', 'email' => $email, 'password' => bcrypt('password123')]);
    }

    private function makeOrg(User $owner): Organization
    {
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);

        return $org;
    }

    public function test_only_an_owner_can_invite_someone(): void
    {
        Mail::fake();
        $owner = $this->makeUser('owner@example.com');
        $member = $this->makeUser('member@example.com');
        $org = $this->makeOrg($owner);
        $org->members()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);
        $this->postJson("/api/orgs/{$org->id}/invites", ['email' => 'new@example.com'])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/orgs/{$org->id}/invites", ['email' => 'new@example.com'])->assertCreated();

        Mail::assertQueued(OrganizationInviteMail::class);
        $this->assertDatabaseHas('organization_invites', ['organization_id' => $org->id, 'email' => 'new@example.com']);
    }

    public function test_invite_show_exposes_organization_and_expiry_state_without_auth(): void
    {
        $owner = $this->makeUser();
        $org = $this->makeOrg($owner);
        $invite = $org->invites()->create([
            'email' => 'invitee@example.com',
            'token' => 'test-token-123',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->getJson("/api/invites/{$invite->token}")->assertOk();

        $this->assertSame('invitee@example.com', $response->json('email'));
        $this->assertSame('Acme', $response->json('organization.name'));
        $this->assertFalse($response->json('expired'));
        $this->assertFalse($response->json('accepted'));
    }

    public function test_accepting_an_invite_emails_the_organizations_owners(): void
    {
        Mail::fake();
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrg($owner);
        $invitee = $this->makeUser('invitee@example.com');
        $invite = $org->invites()->create([
            'email' => 'invitee@example.com',
            'token' => 'test-token-owner-mail',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($invitee);
        $this->postJson("/api/invites/{$invite->token}/accept")->assertOk();

        Mail::assertQueued(OrganizationMemberJoinedMail::class, fn ($mail) => $mail->hasTo($owner->email) && $mail->newMember->is($invitee));
    }

    public function test_accepting_an_invite_adds_the_user_as_a_member(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrg($owner);
        $invitee = $this->makeUser('invitee@example.com');
        $invite = $org->invites()->create([
            'email' => 'invitee@example.com',
            'token' => 'test-token-456',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($invitee);
        $this->postJson("/api/invites/{$invite->token}/accept")->assertOk();

        $this->assertTrue($org->fresh()->isMember($invitee));
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_cannot_accept_an_invite_sent_to_a_different_email(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrg($owner);
        $stranger = $this->makeUser('stranger@example.com');
        $invite = $org->invites()->create([
            'email' => 'invitee@example.com',
            'token' => 'test-token-789',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/invites/{$invite->token}/accept")->assertForbidden();
        $this->assertFalse($org->fresh()->isMember($stranger));
    }

    public function test_cannot_accept_an_expired_invite(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrg($owner);
        $invitee = $this->makeUser('invitee@example.com');
        $invite = $org->invites()->create([
            'email' => 'invitee@example.com',
            'token' => 'expired-token',
            'invited_by' => $owner->id,
            'expires_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($invitee);
        $this->postJson("/api/invites/{$invite->token}/accept")->assertUnprocessable();
        $this->assertFalse($org->fresh()->isMember($invitee));
    }

    public function test_cannot_accept_an_already_accepted_invite_twice(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrg($owner);
        $invitee = $this->makeUser('invitee@example.com');
        $invite = $org->invites()->create([
            'email' => 'invitee@example.com',
            'token' => 'used-token',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($invitee);
        $this->postJson("/api/invites/{$invite->token}/accept")->assertUnprocessable();
    }

    public function test_owner_can_list_and_revoke_a_pending_invite(): void
    {
        Mail::fake();
        $owner = $this->makeUser();
        $org = $this->makeOrg($owner);
        Sanctum::actingAs($owner);
        $invite = $this->postJson("/api/orgs/{$org->id}/invites", ['email' => 'pending@example.com'])->assertCreated();

        $list = $this->getJson("/api/orgs/{$org->id}/invites")->assertOk();
        $this->assertCount(1, $list->json());

        $this->deleteJson("/api/orgs/{$org->id}/invites/{$invite->json('id')}")->assertOk();
        $this->assertDatabaseMissing('organization_invites', ['id' => $invite->json('id')]);
    }

    public function test_any_member_can_list_members_but_only_an_owner_can_remove_one(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $member = $this->makeUser('member@example.com');
        $org = $this->makeOrg($owner);
        $org->members()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);
        $this->getJson("/api/orgs/{$org->id}/members")->assertOk()->assertJsonCount(2);
        $this->deleteJson("/api/orgs/{$org->id}/members/{$owner->id}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/orgs/{$org->id}/members/{$member->id}")->assertOk();
        $this->assertFalse($org->fresh()->isMember($member));
    }

    public function test_the_last_owner_of_an_organization_cannot_be_removed(): void
    {
        $owner = $this->makeUser();
        $org = $this->makeOrg($owner);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/orgs/{$org->id}/members/{$owner->id}")->assertUnprocessable();
        $this->assertTrue($org->fresh()->isMember($owner));
    }

    public function test_a_stranger_cannot_view_or_manage_members_or_invites(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $stranger = $this->makeUser('stranger@example.com');
        $org = $this->makeOrg($owner);

        Sanctum::actingAs($stranger);
        $this->getJson("/api/orgs/{$org->id}/members")->assertForbidden();
        $this->getJson("/api/orgs/{$org->id}/invites")->assertForbidden();
        $this->postJson("/api/orgs/{$org->id}/invites", ['email' => 'x@example.com'])->assertForbidden();
    }
}

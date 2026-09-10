<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Organizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrgControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'user@example.com'): Organizer
    {
        $organizer = Organizer::create(['name' => 'Test User', 'email' => $email, 'password' => bcrypt('password123')]);
        // Neither is mass-assignable (see Organizer::$fillable).
        $organizer->forceFill(['email_verified_at' => now(), 'approval_status' => 'approved'])->save();

        return $organizer;
    }

    private function makeAdmin(string $email = 'admin@example.com'): Organizer
    {
        $admin = $this->makeUser($email);
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    public function test_creating_an_organization_makes_the_creator_its_owner(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $response = $this->postJson('/api/orgs', ['name' => 'Acme Robotics Club'])->assertCreated();

        $this->assertSame('Acme Robotics Club', $response->json('name'));
        $this->assertSame('acme-robotics-club', $response->json('slug'));
    }

    /** Organizations are admin-created now - an ordinary organizer can't self-serve one. */
    public function test_a_non_admin_organizer_cannot_create_an_organization(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/orgs', ['name' => 'Not Allowed'])->assertForbidden();
    }

    public function test_creating_two_organizations_with_the_same_name_gets_unique_slugs(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $first = $this->postJson('/api/orgs', ['name' => 'Acme Club'])->assertCreated();
        $second = $this->postJson('/api/orgs', ['name' => 'Acme Club'])->assertCreated();

        $this->assertNotSame($first->json('slug'), $second->json('slug'));
    }

    public function test_mine_lists_organizations_the_user_belongs_to_with_their_role(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $org = $this->postJson('/api/orgs', ['name' => 'My Club'])->assertCreated();
        $organizer = $this->makeUser();
        $organizer->organizations()->attach($org->json('id'), ['role' => 'member']);

        Sanctum::actingAs($organizer);
        $response = $this->getJson('/api/orgs/mine')->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame('member', $response->json('0.pivot.role'));
    }

    /** An admin manages every organization, not just ones they personally belong to - see OrgController::mine(). */
    public function test_mine_lists_every_organization_for_an_admin_regardless_of_membership(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $this->postJson('/api/orgs', ['name' => 'Admin-created Club'])->assertCreated();

        $otherAdmin = $this->makeAdmin('other-admin@example.com');
        Sanctum::actingAs($otherAdmin);
        $this->postJson('/api/orgs', ['name' => 'Other Admin Club'])->assertCreated();

        $response = $this->getJson('/api/orgs/mine')->assertOk();

        $this->assertCount(2, $response->json());
    }

    public function test_only_an_owner_can_update_the_organization_profile(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $member = $this->makeUser('member@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);
        $org->members()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);
        $this->putJson("/api/orgs/{$org->id}", ['name' => 'New Name'])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->putJson("/api/orgs/{$org->id}", ['name' => 'New Name'])->assertOk();
        $this->assertSame('New Name', $org->fresh()->name);
    }

    public function test_a_stranger_cannot_update_an_organization_they_do_not_belong_to(): void
    {
        $stranger = $this->makeUser('stranger@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

        Sanctum::actingAs($stranger);
        $this->putJson("/api/orgs/{$org->id}", ['name' => 'Hijacked'])->assertForbidden();
    }

    public function test_only_an_owner_can_upload_the_organization_logo(): void
    {
        Storage::fake('public');
        $owner = $this->makeUser('owner@example.com');
        $member = $this->makeUser('member@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);
        $org->members()->attach($member->id, ['role' => 'member']);
        $file = UploadedFile::fake()->image('logo.jpg', 200, 200);

        Sanctum::actingAs($member);
        $this->postJson("/api/orgs/{$org->id}/logo", ['logo' => $file])->assertForbidden();

        Sanctum::actingAs($owner);
        $response = $this->postJson("/api/orgs/{$org->id}/logo", ['logo' => $file])->assertOk();
        $this->assertNotEmpty($response->json('logo'));
    }

    public function test_list_is_public_and_returns_only_id_and_name(): void
    {
        Organization::create(['name' => 'Acme', 'slug' => 'acme', 'email' => 'hello@acme.test']);

        $response = $this->getJson('/api/orgs/list')->assertOk();

        $this->assertSame(['id', 'name'], array_keys($response->json('0')));
    }

    public function test_admin_can_manage_an_organization_they_do_not_belong_to(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);
        $admin = $this->makeAdmin();

        Sanctum::actingAs($admin);
        $this->putJson("/api/orgs/{$org->id}", ['name' => 'Renamed by admin'])->assertOk();
        $this->assertSame('Renamed by admin', $org->fresh()->name);
        $this->getJson("/api/orgs/{$org->id}/members")->assertOk();
    }

    public function test_admin_can_delete_an_organization_and_its_events_survive_unaffiliated(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $org = $this->postJson('/api/orgs', ['name' => 'Acme'])->assertCreated();
        $event = \App\Models\Event::create([
            'title' => 'Meetup', 'slug' => 'meetup', 'organizer_id' => $admin->id,
            'organization_id' => $org->json('id'), 'status' => 'approved', 'capacity' => 0,
        ]);

        $this->deleteJson("/api/orgs/{$org->json('id')}")->assertOk();

        $this->assertDatabaseMissing('organizations', ['id' => $org->json('id')]);
        $this->assertNull($event->fresh()->organization_id);
    }

    public function test_a_non_admin_cannot_delete_an_organization(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/orgs/{$org->id}")->assertForbidden();
        $this->assertDatabaseHas('organizations', ['id' => $org->id]);
    }

    public function test_an_owner_can_promote_a_member_to_owner(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $member = $this->makeUser('member@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);
        $org->members()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/orgs/{$org->id}/members/{$member->id}/promote")->assertOk();

        $this->assertTrue($org->fresh()->isOwner($member->fresh()));
    }

    public function test_a_member_cannot_promote_themselves(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $member = $this->makeUser('member@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);
        $org->members()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);
        $this->postJson("/api/orgs/{$org->id}/members/{$member->id}/promote")->assertForbidden();
    }

    public function test_an_owner_can_demote_a_co_owner_but_not_the_last_owner(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $coOwner = $this->makeUser('co-owner@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);
        $org->members()->attach($coOwner->id, ['role' => 'owner']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/orgs/{$org->id}/members/{$coOwner->id}/demote")->assertOk();
        $this->assertFalse($org->fresh()->isOwner($coOwner->fresh()));

        // Only one owner left now - demoting them must be blocked.
        $this->postJson("/api/orgs/{$org->id}/members/{$owner->id}/demote")->assertStatus(422);
        $this->assertTrue($org->fresh()->isOwner($owner->fresh()));
    }

    public function test_an_admin_can_promote_a_member_in_an_organization_they_do_not_belong_to(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $member = $this->makeUser('member@example.com');
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $org->members()->attach($owner->id, ['role' => 'owner']);
        $org->members()->attach($member->id, ['role' => 'member']);
        $admin = $this->makeAdmin();

        Sanctum::actingAs($admin);
        $this->postJson("/api/orgs/{$org->id}/members/{$member->id}/promote")->assertOk();

        $this->assertTrue($org->fresh()->isOwner($member->fresh()));
    }
}

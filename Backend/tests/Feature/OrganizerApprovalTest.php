<?php

namespace Tests\Feature;

use App\Models\Organizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizerApprovalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The realistic "just signed up and verified their email" state -
     * approval_status is left at the column's own 'pending' default (never
     * mass-assignable, see Organizer::$fillable), matching a real account
     * that's never been through OrganizerAuthController::register() then
     * admin approval.
     */
    private function makeVerifiedUnapprovedOrganizer(string $email = 'newbie@example.com'): Organizer
    {
        $organizer = Organizer::create(['name' => 'Newbie', 'email' => $email, 'password' => bcrypt('password123')]);
        $organizer->forceFill(['email_verified_at' => now()])->save();

        return $organizer;
    }

    private function makeAdmin(string $email = 'admin@example.com'): Organizer
    {
        $admin = Organizer::create(['name' => 'Admin', 'email' => $email, 'password' => bcrypt('password123')]);
        $admin->forceFill(['role' => 'admin', 'email_verified_at' => now(), 'approval_status' => 'approved'])->save();

        return $admin;
    }

    public function test_a_pending_organizer_is_blocked_from_organizer_tooling(): void
    {
        Sanctum::actingAs($this->makeVerifiedUnapprovedOrganizer());

        $this->postJson('/api/events', ['title' => 'My Event'])->assertForbidden();
        $this->getJson('/api/admin/events')->assertForbidden();
        $this->getJson('/api/analytics')->assertForbidden();
        $this->postJson('/api/orgs', ['name' => 'My Club'])->assertForbidden();
    }

    public function test_only_an_admin_can_list_or_decide_pending_organizers(): void
    {
        $organizer = $this->makeVerifiedUnapprovedOrganizer();
        Sanctum::actingAs($organizer);

        $this->getJson('/api/organizers/pending')->assertForbidden();
        $this->postJson("/api/organizers/{$organizer->id}/approve")->assertForbidden();
        $this->postJson("/api/organizers/{$organizer->id}/reject")->assertForbidden();
    }

    public function test_admin_can_approve_a_pending_organizer_who_can_then_use_organizer_tooling(): void
    {
        $organizer = $this->makeVerifiedUnapprovedOrganizer();
        $admin = $this->makeAdmin();

        Sanctum::actingAs($admin);
        $pending = $this->getJson('/api/organizers/pending')->assertOk();
        $this->assertCount(1, $pending->json());
        $this->assertSame($organizer->id, $pending->json('0.id'));

        $this->postJson("/api/organizers/{$organizer->id}/approve")
            ->assertOk()
            ->assertJsonPath('approvalStatus', 'approved');

        $this->assertSame('approved', $organizer->fresh()->approval_status);
        $this->assertSame($admin->id, $organizer->fresh()->approved_by);
        $this->assertNotNull($organizer->fresh()->approved_at);

        // Sanctum::actingAs() pins the resolved user instance for the guard -
        // re-fetch it so the request sees the just-approved status rather
        // than the stale in-memory copy from before the approve() call.
        Sanctum::actingAs($organizer->fresh());
        $this->getJson('/api/admin/events')->assertOk();
    }

    public function test_approving_an_organizer_who_requested_an_organization_grants_membership(): void
    {
        $org = \App\Models\Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $organizer = $this->makeVerifiedUnapprovedOrganizer();
        $organizer->forceFill(['requested_organization_id' => $org->id])->save();

        Sanctum::actingAs($this->makeAdmin());
        $this->postJson("/api/organizers/{$organizer->id}/approve")->assertOk();

        $this->assertTrue($org->fresh()->isMember($organizer->fresh()));
        $this->assertSame('member', $organizer->fresh()->organizations()->first()->pivot->role);
    }

    public function test_the_pending_list_shows_who_the_applicant_is_and_what_organization_they_want(): void
    {
        $admin = $this->makeAdmin();
        $applicant = $this->makeVerifiedUnapprovedOrganizer();
        $applicant->forceFill([
            'contact_number' => '0917 123 4567',
            'requested_organization_name' => 'Brand New Club',
            'requested_organization_address' => '12 Rizal St, Quezon City',
        ])->save();

        Sanctum::actingAs($admin);
        $pending = $this->getJson('/api/organizers/pending')->assertOk();

        $this->assertSame('0917 123 4567', $pending->json('0.contactNumber'));
        $this->assertSame('Brand New Club', $pending->json('0.requestedOrganizationName'));
        $this->assertSame('12 Rizal St, Quezon City', $pending->json('0.requestedOrganizationAddress'));
    }

    public function test_approving_can_create_the_requested_organization_and_make_the_applicant_its_owner(): void
    {
        $applicant = $this->makeVerifiedUnapprovedOrganizer();
        $applicant->forceFill([
            'requested_organization_name' => 'Brand New Club',
            'requested_organization_address' => '12 Rizal St, Quezon City',
        ])->save();

        Sanctum::actingAs($this->makeAdmin());
        $this->postJson("/api/organizers/{$applicant->id}/approve", ['createOrganization' => true])->assertOk();

        $organization = \App\Models\Organization::where('name', 'Brand New Club')->first();
        $this->assertNotNull($organization);
        $this->assertSame('brand-new-club', $organization->slug);
        $this->assertSame('12 Rizal St, Quezon City', $organization->makeVisible('address')->address);
        $this->assertTrue($organization->isOwner($applicant->fresh()));
        $this->assertSame($organization->id, $applicant->fresh()->requested_organization_id);
    }

    public function test_approving_without_asking_to_create_the_organization_creates_nothing(): void
    {
        $applicant = $this->makeVerifiedUnapprovedOrganizer();
        $applicant->forceFill(['requested_organization_name' => 'Brand New Club', 'requested_organization_address' => '12 Rizal St'])->save();

        Sanctum::actingAs($this->makeAdmin());
        $this->postJson("/api/organizers/{$applicant->id}/approve")->assertOk();

        $this->assertDatabaseMissing('organizations', ['name' => 'Brand New Club']);
        $this->assertSame('approved', $applicant->fresh()->approval_status);
    }

    public function test_create_organization_is_ignored_when_the_applicant_picked_an_existing_one(): void
    {
        $existing = \App\Models\Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $applicant = $this->makeVerifiedUnapprovedOrganizer();
        $applicant->forceFill(['requested_organization_id' => $existing->id, 'requested_organization_name' => 'Should Not Exist'])->save();

        Sanctum::actingAs($this->makeAdmin());
        $this->postJson("/api/organizers/{$applicant->id}/approve", ['createOrganization' => true])->assertOk();

        $this->assertDatabaseMissing('organizations', ['name' => 'Should Not Exist']);
        // Joins the existing one as a plain member, never an owner.
        $this->assertTrue($existing->isMember($applicant->fresh()));
        $this->assertFalse($existing->isOwner($applicant->fresh()));
    }

    public function test_history_lists_decided_applications_newest_first_without_pending_or_admins(): void
    {
        $admin = $this->makeAdmin();
        $pending = $this->makeVerifiedUnapprovedOrganizer('pending@example.com');
        $older = $this->makeVerifiedUnapprovedOrganizer('older@example.com');
        $newer = $this->makeVerifiedUnapprovedOrganizer('newer@example.com');

        Sanctum::actingAs($admin);
        $this->postJson("/api/organizers/{$older->id}/approve")->assertOk();
        $this->travel(1)->hour();
        $this->postJson("/api/organizers/{$newer->id}/reject")->assertOk();

        $history = $this->getJson('/api/organizers/history')->assertOk();

        $this->assertSame(['newer@example.com', 'older@example.com'], collect($history->json())->pluck('email')->all());
        $this->assertSame(['rejected', 'approved'], collect($history->json())->pluck('approvalStatus')->all());
        $this->assertSame($admin->name, $history->json('0.approver.name'));
        $this->assertNotContains($pending->email, collect($history->json())->pluck('email')->all());
        $this->assertNotContains($admin->email, collect($history->json())->pluck('email')->all());
    }

    public function test_only_an_admin_can_read_the_approval_history(): void
    {
        // Approved, so the organizer.approved middleware lets them through
        // and it's specifically the admin check that says no.
        $organizer = $this->makeVerifiedUnapprovedOrganizer();
        $organizer->forceFill(['approval_status' => 'approved'])->save();

        Sanctum::actingAs($organizer);
        $this->getJson('/api/organizers/history')->assertForbidden();
    }

    public function test_admin_can_reject_a_pending_organizer_who_stays_blocked(): void
    {
        $organizer = $this->makeVerifiedUnapprovedOrganizer();
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson("/api/organizers/{$organizer->id}/reject")
            ->assertOk()
            ->assertJsonPath('approvalStatus', 'rejected');

        Sanctum::actingAs($organizer);
        $this->getJson('/api/admin/events')->assertForbidden();
    }

    /**
     * Every account that existed before this migration ran was grandfathered
     * straight to 'approved' - confirmed here the same way the migration
     * itself does it, rather than trusting RefreshDatabase's fresh-migration
     * timing to prove anything about real pre-existing production data.
     */
    public function test_an_admin_created_before_this_feature_existed_is_not_locked_out(): void
    {
        $admin = Organizer::create(['name' => 'Legacy Admin', 'email' => 'legacy@example.com', 'password' => bcrypt('password123')]);
        $admin->forceFill(['role' => 'admin', 'email_verified_at' => now(), 'approval_status' => 'approved', 'approved_at' => now()])->save();

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/events')->assertOk();
    }
}

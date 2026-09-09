<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Organizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrganization(Organizer $owner): Organization
    {
        $org = Organization::create(['name' => "{$owner->name}'s Org", 'slug' => 'org-'.uniqid()]);
        $org->members()->attach($owner->id, ['role' => 'owner']);

        return $org;
    }

    // Neither is mass-assignable (see Organizer::$fillable) - only the
    // Sanctum::actingAs() actor in each test needs this, not every
    // organizer created, since the 'verified'/'organizer.approved' route
    // gates check the requester.
    private function verify(Organizer $organizer): Organizer
    {
        $organizer->forceFill(['email_verified_at' => now(), 'approval_status' => 'approved'])->save();

        return $organizer;
    }

    public function test_analytics_only_counts_the_organizers_own_events(): void
    {
        $owner = $this->verify(Organizer::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]));
        $stranger = Organizer::create(['name' => 'Stranger', 'email' => 'stranger@example.com', 'password' => bcrypt('password123')]);
        $ownerOrg = $this->makeOrganization($owner);
        $strangerOrg = $this->makeOrganization($stranger);

        $ownedEvent = Event::create(['title' => 'Mine', 'status' => 'approved', 'slug' => 'mine-'.uniqid(), 'organizer_id' => $owner->id, 'organization_id' => $ownerOrg->id]);
        $ownedEvent->registrations()->create(['name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A', 'attended' => true]);

        $othersEvent = Event::create(['title' => 'Theirs', 'status' => 'approved', 'slug' => 'theirs-'.uniqid(), 'organizer_id' => $stranger->id, 'organization_id' => $strangerOrg->id]);
        $othersEvent->registrations()->create(['name' => 'B', 'email' => 'b@example.com', 'qr_code' => 'QR-B', 'attended' => true]);
        $othersEvent->registrations()->create(['name' => 'C', 'email' => 'c@example.com', 'qr_code' => 'QR-C', 'attended' => true]);

        Sanctum::actingAs($owner);
        $response = $this->getJson('/api/analytics')->assertOk();

        $this->assertSame(1, $response->json('totalEvents'));
        $this->assertSame(1, $response->json('totalRegistrations'));
    }

    /**
     * approve()/reject() are strictly admin-gated with no "you created this
     * one" exception, so pendingApprovals must never be non-zero for a
     * non-admin - it drives a banner that links straight to the admin-only
     * Approvals page. Confirmed live by two independent testers: a
     * non-admin saw "N events awaiting approval" and got "Admins only" on
     * clicking through.
     */
    public function test_pending_approvals_is_always_zero_for_a_non_admin(): void
    {
        $owner = $this->verify(Organizer::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]));
        $ownerOrg = $this->makeOrganization($owner);
        Event::create(['title' => 'Mine Pending', 'status' => 'pending', 'slug' => 'mine-pending-'.uniqid(), 'organizer_id' => $owner->id, 'organization_id' => $ownerOrg->id]);
        // A pending, org-less event belonging to someone else entirely -
        // this is what the reported bug actually surfaced: org-less events
        // are deliberately "anyone's to manage" as a legacy-data safety net
        // elsewhere in the app, but that should never extend to the
        // strictly-admin approve/reject action.
        Event::create(['title' => 'Unrelated Org-less Pending', 'status' => 'pending', 'slug' => 'unrelated-pending-'.uniqid()]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/analytics')->assertOk()->assertJsonPath('pendingApprovals', 0);
    }

    public function test_a_co_member_sees_the_same_organizations_events_as_the_owner(): void
    {
        $owner = Organizer::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]);
        $coMember = $this->verify(Organizer::create(['name' => 'CoMember', 'email' => 'comember@example.com', 'password' => bcrypt('password123')]));
        $org = $this->makeOrganization($owner);
        $org->members()->attach($coMember->id, ['role' => 'member']);

        Event::create(['title' => 'Club Event', 'status' => 'approved', 'slug' => 'club-event-'.uniqid(), 'organizer_id' => $owner->id, 'organization_id' => $org->id]);

        Sanctum::actingAs($coMember);
        $this->getJson('/api/analytics')->assertOk()->assertJsonPath('totalEvents', 1);
    }

    public function test_analytics_shows_everything_to_an_admin(): void
    {
        $admin = Organizer::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password123')]);
        $admin->forceFill(['role' => 'admin', 'email_verified_at' => now(), 'approval_status' => 'approved'])->save();
        $owner = Organizer::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]);
        Event::create(['title' => 'Mine', 'status' => 'pending', 'slug' => 'mine-'.uniqid(), 'organizer_id' => $owner->id]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/analytics')->assertOk()->assertJsonPath('pendingApprovals', 1);
    }
}

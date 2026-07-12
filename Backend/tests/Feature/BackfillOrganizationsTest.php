<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillOrganizationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_event_owning_user_gets_their_own_organization(): void
    {
        $owner = User::create(['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'password' => bcrypt('password123')]);
        $event = Event::create(['title' => 'Test Event', 'status' => 'approved', 'slug' => 'test-event', 'user_id' => $owner->id]);

        $this->artisan('organizations:backfill')->assertExitCode(0);

        $owner->refresh();
        $this->assertCount(1, $owner->organizations);
        $this->assertSame('owner', $owner->organizations->first()->pivot->role);
        $this->assertNotNull($owner->organizations->first()->slug);
        $this->assertSame($owner->organizations->first()->id, $event->fresh()->organization_id);
    }

    public function test_the_demo_account_gets_the_existing_singleton_organization_reassigned(): void
    {
        $singleton = Organization::create(['id' => 1, 'name' => 'QRMeets Community']);
        $demo = User::create(['name' => 'Demo Organizer', 'email' => 'demo@qrmeets.net', 'password' => bcrypt('password123')]);
        Event::create(['title' => 'Demo Event', 'status' => 'approved', 'slug' => 'demo-event', 'user_id' => $demo->id]);

        $this->artisan('organizations:backfill')->assertExitCode(0);

        $demo->refresh();
        $this->assertCount(1, $demo->organizations);
        $this->assertSame($singleton->id, $demo->organizations->first()->id);
        $this->assertNotNull($singleton->fresh()->slug);
    }

    public function test_backfill_is_idempotent(): void
    {
        $owner = User::create(['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'password' => bcrypt('password123')]);
        Event::create(['title' => 'Test Event', 'status' => 'approved', 'slug' => 'test-event', 'user_id' => $owner->id]);

        $this->artisan('organizations:backfill');
        $firstOrgId = $owner->fresh()->organizations->first()->id;

        $this->artisan('organizations:backfill');
        $owner->refresh();

        $this->assertCount(1, $owner->organizations);
        $this->assertSame($firstOrgId, $owner->organizations->first()->id);
        $this->assertSame(1, Organization::count());
    }

    public function test_events_with_no_owner_are_left_without_an_organization(): void
    {
        Event::create(['title' => 'Legacy Event', 'status' => 'approved', 'slug' => 'legacy-event', 'user_id' => null]);

        $this->artisan('organizations:backfill')->assertExitCode(0);

        $this->assertSame(0, Organization::count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOrgPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_org_page_shows_branding_and_splits_events_into_upcoming_and_past(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]);
        $org = Organization::create(['name' => 'Acme Robotics', 'slug' => 'acme-robotics', 'description' => 'A robotics club']);
        $org->members()->attach($owner->id, ['role' => 'owner']);

        Event::create(['title' => 'Upcoming Meetup', 'status' => 'approved', 'is_private' => false, 'date' => now()->addWeek()->toDateString(), 'slug' => 'upcoming-meetup', 'user_id' => $owner->id, 'organization_id' => $org->id]);
        Event::create(['title' => 'Past Meetup', 'status' => 'approved', 'is_private' => false, 'date' => now()->subWeek()->toDateString(), 'slug' => 'past-meetup', 'user_id' => $owner->id, 'organization_id' => $org->id]);
        Event::create(['title' => 'Pending Meetup', 'status' => 'pending', 'is_private' => false, 'date' => now()->addWeek()->toDateString(), 'slug' => 'pending-meetup', 'user_id' => $owner->id, 'organization_id' => $org->id]);
        Event::create(['title' => 'Private Meetup', 'status' => 'approved', 'is_private' => true, 'date' => now()->addWeek()->toDateString(), 'slug' => 'private-meetup', 'user_id' => $owner->id, 'organization_id' => $org->id]);

        $response = $this->getJson('/api/org/acme-robotics')->assertOk();

        $this->assertSame('Acme Robotics', $response->json('organization.name'));
        $this->assertCount(1, $response->json('upcomingEvents'));
        $this->assertSame('Upcoming Meetup', $response->json('upcomingEvents.0.title'));
        $this->assertCount(1, $response->json('pastEvents'));
        $this->assertSame('Past Meetup', $response->json('pastEvents.0.title'));
    }

    public function test_public_org_page_404s_for_an_unknown_slug(): void
    {
        $this->getJson('/api/org/does-not-exist')->assertNotFound();
    }

    public function test_directory_only_lists_organizations_with_a_real_public_event(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]);
        $active = Organization::create(['name' => 'Acme Robotics', 'slug' => 'acme-robotics']);
        $empty = Organization::create(['name' => 'Empty Org', 'slug' => 'empty-org']);
        $privateOnly = Organization::create(['name' => 'Private Only', 'slug' => 'private-only']);

        Event::create(['title' => 'Upcoming', 'status' => 'approved', 'is_private' => false, 'date' => now()->addWeek()->toDateString(), 'slug' => 'upcoming', 'user_id' => $owner->id, 'organization_id' => $active->id]);
        Event::create(['title' => 'Private', 'status' => 'approved', 'is_private' => true, 'date' => now()->addWeek()->toDateString(), 'slug' => 'private', 'user_id' => $owner->id, 'organization_id' => $privateOnly->id]);

        $response = $this->getJson('/api/orgs')->assertOk();

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Acme Robotics'));
        $this->assertFalse($names->contains('Empty Org'));
        $this->assertFalse($names->contains('Private Only'));
        $this->assertSame(1, collect($response->json())->firstWhere('name', 'Acme Robotics')['upcomingEventsCount']);
    }
}

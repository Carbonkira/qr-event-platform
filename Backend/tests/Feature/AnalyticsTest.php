<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_only_counts_the_organizers_own_events(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]);
        $stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@example.com', 'password' => bcrypt('password123')]);

        $ownedEvent = Event::create(['title' => 'Mine', 'status' => 'approved', 'slug' => 'mine-'.uniqid(), 'user_id' => $owner->id]);
        $ownedEvent->registrations()->create(['name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A', 'attended' => true]);

        $othersEvent = Event::create(['title' => 'Theirs', 'status' => 'approved', 'slug' => 'theirs-'.uniqid(), 'user_id' => $stranger->id]);
        $othersEvent->registrations()->create(['name' => 'B', 'email' => 'b@example.com', 'qr_code' => 'QR-B', 'attended' => true]);
        $othersEvent->registrations()->create(['name' => 'C', 'email' => 'c@example.com', 'qr_code' => 'QR-C', 'attended' => true]);

        Sanctum::actingAs($owner);
        $response = $this->getJson('/api/analytics')->assertOk();

        $this->assertSame(1, $response->json('totalEvents'));
        $this->assertSame(1, $response->json('totalRegistrations'));
    }

    public function test_pending_approvals_count_is_scoped_to_the_organizers_own_events(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]);
        $stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@example.com', 'password' => bcrypt('password123')]);

        Event::create(['title' => 'Mine Pending', 'status' => 'pending', 'slug' => 'mine-pending-'.uniqid(), 'user_id' => $owner->id]);
        Event::create(['title' => 'Theirs Pending', 'status' => 'pending', 'slug' => 'theirs-pending-'.uniqid(), 'user_id' => $stranger->id]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/analytics')->assertOk()->assertJsonPath('pendingApprovals', 1);
    }

    public function test_analytics_shows_everything_to_an_admin(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password123')]);
        $admin->forceFill(['role' => 'admin'])->save();
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password123')]);
        Event::create(['title' => 'Mine', 'status' => 'pending', 'slug' => 'mine-'.uniqid(), 'user_id' => $owner->id]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/analytics')->assertOk()->assertJsonPath('pendingApprovals', 1);
    }
}

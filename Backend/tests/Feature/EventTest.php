<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'organizer@example.com'): User
    {
        $user = User::create(['name' => 'Organizer', 'email' => $email, 'password' => bcrypt('password123')]);
        // email_verified_at isn't mass-assignable (see User::$fillable).
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function test_public_listing_sorts_by_distance_when_coordinates_are_given(): void
    {
        // Manila
        Event::create(['title' => 'Near', 'status' => 'approved', 'is_private' => false, 'slug' => 'near', 'lat' => 14.5995, 'lng' => 120.9842]);
        // Cebu, further from the Manila point we'll query with below
        Event::create(['title' => 'Far', 'status' => 'approved', 'is_private' => false, 'slug' => 'far', 'lat' => 10.3157, 'lng' => 123.8854]);
        // No coordinates set - should sort last, not be excluded
        Event::create(['title' => 'No Coordinates', 'status' => 'approved', 'is_private' => false, 'slug' => 'no-coords']);

        $response = $this->getJson('/api/events?lat=14.5995&lng=120.9842')->assertOk();

        $titles = collect($response->json())->pluck('title')->all();
        $this->assertSame(['Near', 'Far', 'No Coordinates'], $titles);
        $this->assertEquals(0.0, $response->json('0.distanceKm'));
        $this->assertNull($response->json('2.distanceKm'));
    }

    public function test_public_listing_ignores_invalid_coordinates(): void
    {
        Event::create(['title' => 'Some Event', 'status' => 'approved', 'is_private' => false, 'slug' => 'some-event']);

        $this->getJson('/api/events?lat=999&lng=999')->assertStatus(422);
    }

    public function test_an_unverified_account_cannot_create_an_event(): void
    {
        $unverified = User::create(['name' => 'Unverified', 'email' => 'unverified@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($unverified);

        $this->postJson('/api/events', ['title' => 'My Draft Event', 'saveAsDraft' => true])
            ->assertForbidden();
    }

    public function test_a_draft_can_be_saved_with_only_a_title(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/events', ['title' => 'My Draft Event', 'saveAsDraft' => true])
            ->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('title', 'My Draft Event');
    }

    public function test_a_pending_event_requires_the_full_field_set(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/events', ['title' => 'Incomplete Event'])
            ->assertStatus(422);
    }

    public function test_submitting_an_incomplete_draft_for_approval_fails_with_field_errors(): void
    {
        Sanctum::actingAs($user = $this->makeUser());
        $event = Event::create(['title' => 'Bare Draft', 'status' => 'draft', 'user_id' => $user->id, 'slug' => 'bare-draft']);

        $this->postJson("/api/events/{$event->id}/submit")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'venue', 'date', 'startTime', 'endTime', 'capacity']);
    }

    public function test_submitting_a_complete_draft_moves_it_to_pending(): void
    {
        Sanctum::actingAs($user = $this->makeUser());
        $event = Event::create([
            'title' => 'Complete Draft', 'status' => 'draft', 'user_id' => $user->id, 'slug' => 'complete-draft',
            'type' => 'Meetup', 'venue' => 'Venue', 'date' => '2026-08-01', 'start_time' => '10:00', 'end_time' => '12:00', 'capacity' => 30,
        ]);

        $this->postJson("/api/events/{$event->id}/submit")
            ->assertOk()
            ->assertJsonPath('status', 'pending');
    }

    public function test_only_the_owner_can_update_an_event(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $stranger = $this->makeUser('stranger@example.com');
        $event = Event::create(['title' => 'Owned Event', 'status' => 'pending', 'user_id' => $owner->id, 'slug' => 'owned-event', 'capacity' => 10]);

        Sanctum::actingAs($stranger);
        $this->putJson("/api/events/{$event->id}", ['capacity' => 999])->assertForbidden();
        $this->assertSame(10, $event->fresh()->capacity);

        Sanctum::actingAs($owner);
        $this->putJson("/api/events/{$event->id}", ['capacity' => 999])->assertOk();
        $this->assertSame(999, $event->fresh()->capacity);
    }

    public function test_only_the_owner_can_delete_an_event(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $stranger = $this->makeUser('stranger@example.com');
        $event = Event::create(['title' => 'Owned Event', 'status' => 'pending', 'user_id' => $owner->id, 'slug' => 'owned-event']);

        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/events/{$event->id}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/events/{$event->id}")->assertOk();
    }

    public function test_a_legacy_event_with_no_owner_can_be_edited_by_anyone(): void
    {
        Sanctum::actingAs($this->makeUser());
        $event = Event::create(['title' => 'Legacy Event', 'status' => 'pending', 'user_id' => null, 'slug' => 'legacy-event', 'capacity' => 10]);

        $this->putJson("/api/events/{$event->id}", ['capacity' => 50])->assertOk();
    }

    public function test_anyone_can_duplicate_someone_elses_event(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $other = $this->makeUser('other@example.com');
        $event = Event::create(['title' => 'Original', 'status' => 'approved', 'user_id' => $owner->id, 'slug' => 'original']);

        Sanctum::actingAs($other);
        $response = $this->postJson("/api/events/{$event->id}/duplicate")->assertCreated();

        $this->assertSame($other->id, $response->json('userId'));
        $this->assertSame('draft', $response->json('status'));
    }

    public function test_the_owner_can_mark_an_approved_event_completed(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $event = Event::create(['title' => 'Wrapped Up', 'status' => 'approved', 'user_id' => $owner->id, 'slug' => 'wrapped-up']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/events/{$event->id}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    public function test_a_stranger_cannot_mark_someone_elses_event_completed(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $stranger = $this->makeUser('stranger@example.com');
        $event = Event::create(['title' => 'Owned Event', 'status' => 'approved', 'user_id' => $owner->id, 'slug' => 'owned-event']);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/events/{$event->id}/complete")->assertForbidden();
        $this->assertSame('approved', $event->fresh()->status);
    }

    public function test_a_pending_event_cannot_be_marked_completed(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $event = Event::create(['title' => 'Not Yet Approved', 'status' => 'pending', 'user_id' => $owner->id, 'slug' => 'not-yet-approved']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/events/{$event->id}/complete")->assertStatus(422);
    }

    public function test_generate_description_requires_authentication(): void
    {
        $this->postJson('/api/events/generate-description', ['title' => 'My Event'])
            ->assertUnauthorized();
    }

    public function test_generate_description_requires_a_title(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/events/generate-description', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_generate_description_fails_clearly_when_no_gemini_key_is_configured(): void
    {
        config(['services.gemini.key' => null]);
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/events/generate-description', ['title' => 'My Event'])
            ->assertStatus(503);
    }

    public function test_generate_description_returns_the_text_gemini_responds_with(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Join us for a night of Laravel talks and pizza.']]]],
                ],
            ]),
        ]);
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/events/generate-description', ['title' => 'Laravel Meetup'])
            ->assertOk()
            ->assertJsonPath('description', 'Join us for a night of Laravel talks and pizza.');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains($request->url(), 'key=test-key'));
    }

    public function test_upload_image_requires_authentication(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->image('banner.jpg');

        $this->postJson('/api/events/upload-image', ['image' => $file])->assertUnauthorized();
    }

    public function test_upload_image_requires_an_actual_image(): void
    {
        Sanctum::actingAs($this->makeUser());
        $file = \Illuminate\Http\UploadedFile::fake()->create('notes.txt', 10);

        $this->postJson('/api/events/upload-image', ['image' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_upload_image_stores_the_file_and_returns_a_url(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        Sanctum::actingAs($this->makeUser());
        $file = \Illuminate\Http\UploadedFile::fake()->image('banner.jpg', 1200, 600);

        $response = $this->postJson('/api/events/upload-image', ['image' => $file])->assertOk();

        $url = $response->json('url');
        $this->assertNotEmpty($url);
        $storedPath = \Illuminate\Support\Str::after(parse_url($url, PHP_URL_PATH), '/storage/');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($storedPath);
    }

    public function test_only_an_admin_can_approve_or_reject_an_event_even_its_own_creator_cannot(): void
    {
        $organizer = $this->makeUser('organizer@example.com');
        $event = Event::create(['title' => 'Pending Event', 'status' => 'pending', 'user_id' => $organizer->id, 'slug' => 'pending-event']);

        Sanctum::actingAs($organizer);
        $this->postJson("/api/events/{$event->id}/approve")->assertForbidden();
        $this->postJson("/api/events/{$event->id}/reject")->assertForbidden();
        $this->assertSame('pending', $event->fresh()->status);

        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$event->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', 'approved');
    }
}

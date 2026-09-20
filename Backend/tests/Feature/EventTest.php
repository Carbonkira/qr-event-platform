<?php

namespace Tests\Feature;

use App\Mail\EventCancelledMail;
use App\Mail\EventSubmittedForApprovalMail;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Organizer;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'organizer@example.com'): Organizer
    {
        $organizer = Organizer::create(['name' => 'Organizer', 'email' => $email, 'password' => bcrypt('password123')]);
        // Neither is mass-assignable (see Organizer::$fillable).
        $organizer->forceFill(['email_verified_at' => now(), 'approval_status' => 'approved'])->save();

        return $organizer;
    }

    private function makeOrganization(Organizer $owner): Organization
    {
        $org = Organization::create(['name' => "{$owner->name}'s Org", 'slug' => 'org-'.uniqid()]);
        $org->members()->attach($owner->id, ['role' => 'owner']);

        return $org;
    }

    public function test_admin_index_only_shows_events_the_organizer_owns(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $stranger = $this->makeUser('stranger@example.com');
        $ownerOrg = $this->makeOrganization($owner);
        $strangerOrg = $this->makeOrganization($stranger);
        Event::create(['title' => 'Mine', 'status' => 'approved', 'slug' => 'mine-'.uniqid(), 'organizer_id' => $owner->id, 'organization_id' => $ownerOrg->id]);
        Event::create(['title' => 'Theirs', 'status' => 'approved', 'slug' => 'theirs-'.uniqid(), 'organizer_id' => $stranger->id, 'organization_id' => $strangerOrg->id]);
        Event::create(['title' => 'Legacy', 'status' => 'approved', 'slug' => 'legacy-'.uniqid(), 'organizer_id' => null]);

        Sanctum::actingAs($owner);
        $titles = collect($this->getJson('/api/admin/events')->assertOk()->json())->pluck('title')->all();

        $this->assertEqualsCanonicalizing(['Mine', 'Legacy'], $titles);
    }

    public function test_admin_index_shows_everything_to_an_admin(): void
    {
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrganization($owner);
        Event::create(['title' => 'Mine', 'status' => 'approved', 'slug' => 'mine-'.uniqid(), 'organizer_id' => $owner->id, 'organization_id' => $org->id]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/events')->assertOk()->assertJsonCount(1);
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

    public function test_public_listing_and_show_report_a_real_going_count_excluding_waitlist(): void
    {
        $event = Event::create(['title' => 'Some Event', 'status' => 'approved', 'is_private' => false, 'slug' => 'some-event', 'capacity' => 10]);
        $event->registrations()->create(['name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A']);
        $event->registrations()->create(['name' => 'B', 'email' => 'b@example.com', 'qr_code' => 'QR-B']);
        $event->registrations()->create(['name' => 'C', 'email' => 'c@example.com', 'qr_code' => 'QR-C', 'waitlisted' => true]);

        $list = $this->getJson('/api/events')->assertOk();
        $this->assertSame(2, $list->json('0.registrationsCount'));

        $show = $this->getJson('/api/events/some-event')->assertOk();
        $this->assertSame(2, $show->json('registrationsCount'));
    }

    public function test_an_unverified_account_cannot_create_an_event(): void
    {
        $unverified = Organizer::create(['name' => 'Unverified', 'email' => 'unverified@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($unverified);

        $this->postJson('/api/events', ['title' => 'My Draft Event', 'saveAsDraft' => true])
            ->assertForbidden();
    }

    public function test_a_pending_organizer_cannot_create_an_event(): void
    {
        // Verified but never admin-approved (see EnsureOrganizerApproved) -
        // a different gate than email verification, checked separately.
        $pending = Organizer::create(['name' => 'Pending', 'email' => 'pending@example.com', 'password' => bcrypt('password123')]);
        $pending->forceFill(['email_verified_at' => now()])->save();
        Sanctum::actingAs($pending);

        $this->postJson('/api/events', ['title' => 'My Draft Event', 'saveAsDraft' => true])
            ->assertForbidden();
    }

    public function test_a_draft_can_be_saved_with_only_a_title_and_an_organization(): void
    {
        $user = $this->makeUser();
        $org = $this->makeOrganization($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/events', ['title' => 'My Draft Event', 'organizationId' => $org->id, 'saveAsDraft' => true])
            ->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('title', 'My Draft Event');
    }

    /**
     * Organizations are admin-created now (see OrgController::store()) - an
     * organizer can only ever create an event under one they've actually
     * been added to, so this is the hard "ask the admin's permission" gate
     * in practice, not a validation nicety.
     */
    public function test_creating_an_event_without_belonging_to_any_organization_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/events', ['title' => 'My Draft Event', 'saveAsDraft' => true])
            ->assertForbidden();
    }

    public function test_creating_an_event_under_an_organization_you_do_not_belong_to_is_forbidden(): void
    {
        $stranger = $this->makeUser('stranger@example.com');
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrganization($owner);

        Sanctum::actingAs($stranger);
        $this->postJson('/api/events', ['title' => 'Not Yours', 'organizationId' => $org->id, 'saveAsDraft' => true])
            ->assertForbidden();
    }

    /** An admin manages every organization already - no membership needed, and no organization needed at all. */
    public function test_an_admin_can_create_an_event_with_no_organization(): void
    {
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/events', ['title' => 'Unaffiliated Event', 'saveAsDraft' => true])
            ->assertCreated();

        $this->assertNull($response->json('organizationId'));
    }

    public function test_an_admin_can_create_an_event_under_an_organization_they_do_not_belong_to(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrganization($owner);
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/events', ['title' => 'Admin Event', 'organizationId' => $org->id, 'saveAsDraft' => true])
            ->assertCreated();

        $this->assertSame($org->id, $response->json('organizationId'));
    }

    public function test_an_admin_cannot_create_an_event_under_a_nonexistent_organization(): void
    {
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();

        Sanctum::actingAs($admin);
        $this->postJson('/api/events', ['title' => 'Bad Org Event', 'organizationId' => 999999, 'saveAsDraft' => true])
            ->assertStatus(422);
    }

    public function test_a_pending_event_requires_the_full_field_set(): void
    {
        $user = $this->makeUser();
        $org = $this->makeOrganization($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/events', ['title' => 'Incomplete Event', 'organizationId' => $org->id])
            ->assertStatus(422);
    }

    public function test_submitting_an_incomplete_draft_for_approval_fails_with_field_errors(): void
    {
        Sanctum::actingAs($user = $this->makeUser());
        $event = Event::create(['title' => 'Bare Draft', 'status' => 'draft', 'organizer_id' => $user->id, 'slug' => 'bare-draft']);

        $this->postJson("/api/events/{$event->id}/submit")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'venue', 'date', 'startTime', 'endTime', 'capacity']);
    }

    public function test_submitting_a_complete_draft_moves_it_to_pending_and_notifies_admins(): void
    {
        Mail::fake();
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($user = $this->makeUser());
        $event = Event::create([
            'title' => 'Complete Draft', 'status' => 'draft', 'organizer_id' => $user->id, 'slug' => 'complete-draft',
            'type' => 'Meetup', 'venue' => 'Venue', 'date' => '2026-08-01', 'start_time' => '10:00', 'end_time' => '12:00', 'capacity' => 30,
        ]);

        $this->postJson("/api/events/{$event->id}/submit")
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        Mail::assertQueued(EventSubmittedForApprovalMail::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    /**
     * Previously an organization-backed event auto-approved (the org
     * vouched for it); now every event needs the admin's explicit approval
     * regardless of organization backing, since the admin already sits at
     * the top of every chain (organizations are admin-created too).
     */
    public function test_submitting_a_draft_for_an_organization_backed_event_still_needs_admin_approval(): void
    {
        $user = $this->makeUser();
        $org = $this->makeOrganization($user);
        Sanctum::actingAs($user);
        $event = Event::create([
            'title' => 'Club Meetup', 'status' => 'draft', 'organizer_id' => $user->id, 'organization_id' => $org->id, 'slug' => 'club-meetup',
            'type' => 'Meetup', 'venue' => 'Venue', 'date' => '2026-08-01', 'start_time' => '10:00', 'end_time' => '12:00', 'capacity' => 30,
        ]);

        $this->postJson("/api/events/{$event->id}/submit")
            ->assertOk()
            ->assertJsonPath('status', 'pending');
    }

    public function test_creating_an_event_directly_for_an_organization_still_needs_admin_approval(): void
    {
        Mail::fake();
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $user = $this->makeUser();
        $org = $this->makeOrganization($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/events', [
            'title' => 'Club Meetup', 'type' => 'Meetup', 'venue' => 'Venue', 'organizationId' => $org->id,
            'date' => '2026-08-01', 'startTime' => '10:00', 'endTime' => '12:00', 'capacity' => 30,
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        Mail::assertQueued(EventSubmittedForApprovalMail::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_only_the_owner_can_update_an_event(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $stranger = $this->makeUser('stranger@example.com');
        $org = $this->makeOrganization($owner);
        $event = Event::create(['title' => 'Owned Event', 'status' => 'pending', 'organizer_id' => $owner->id, 'organization_id' => $org->id, 'slug' => 'owned-event', 'capacity' => 10]);

        Sanctum::actingAs($stranger);
        $this->putJson("/api/events/{$event->id}", ['capacity' => 999])->assertForbidden();
        $this->assertSame(10, $event->fresh()->capacity);

        Sanctum::actingAs($owner);
        $this->putJson("/api/events/{$event->id}", ['capacity' => 999])->assertOk();
        $this->assertSame(999, $event->fresh()->capacity);
    }

    public function test_a_co_member_who_did_not_create_the_event_can_still_edit_it(): void
    {
        $creator = $this->makeUser('creator@example.com');
        $coMember = $this->makeUser('comember@example.com');
        $org = $this->makeOrganization($creator);
        $org->members()->attach($coMember->id, ['role' => 'member']);
        $event = Event::create(['title' => 'Club Event', 'status' => 'pending', 'organizer_id' => $creator->id, 'organization_id' => $org->id, 'slug' => 'club-event', 'capacity' => 10]);

        Sanctum::actingAs($coMember);
        $this->putJson("/api/events/{$event->id}", ['capacity' => 50])->assertOk();
        $this->assertSame(50, $event->fresh()->capacity);
    }

    public function test_an_admin_can_edit_an_event_under_an_organization_they_do_not_belong_to(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrganization($owner);
        $event = Event::create(['title' => 'Owned Event', 'status' => 'pending', 'organizer_id' => $owner->id, 'organization_id' => $org->id, 'slug' => 'owned-event', 'capacity' => 10]);
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();

        Sanctum::actingAs($admin);
        $this->putJson("/api/events/{$event->id}", ['capacity' => 50])->assertOk();
        $this->assertSame(50, $event->fresh()->capacity);
    }

    public function test_only_the_owner_can_delete_an_event(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $stranger = $this->makeUser('stranger@example.com');
        $org = $this->makeOrganization($owner);
        $event = Event::create(['title' => 'Owned Event', 'status' => 'pending', 'organizer_id' => $owner->id, 'organization_id' => $org->id, 'slug' => 'owned-event']);

        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/events/{$event->id}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/events/{$event->id}")->assertOk();
    }

    public function test_an_approved_or_completed_event_cannot_be_deleted(): void
    {
        $owner = $this->makeUser();
        $org = $this->makeOrganization($owner);
        $approved = Event::create(['title' => 'Live Event', 'status' => 'approved', 'organizer_id' => $owner->id, 'organization_id' => $org->id, 'slug' => 'live-event']);
        $completed = Event::create(['title' => 'Done Event', 'status' => 'completed', 'organizer_id' => $owner->id, 'organization_id' => $org->id, 'slug' => 'done-event']);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/events/{$approved->id}")->assertStatus(422);
        $this->deleteJson("/api/events/{$completed->id}")->assertStatus(422);
        $this->assertNotNull($approved->fresh());
        $this->assertNotNull($completed->fresh());
    }

    public function test_cancelling_an_event_emails_every_registrant_and_stops_it_appearing_publicly(): void
    {
        Mail::fake();
        $owner = $this->makeUser();
        $org = $this->makeOrganization($owner);
        $event = Event::create(['title' => 'Live Event', 'status' => 'approved', 'is_private' => false, 'organizer_id' => $owner->id, 'organization_id' => $org->id, 'slug' => 'live-event']);
        $r1 = Registration::create(['event_id' => $event->id, 'name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A']);
        $r2 = Registration::create(['event_id' => $event->id, 'name' => 'B', 'email' => 'b@example.com', 'qr_code' => 'QR-B', 'waitlisted' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/events/{$event->id}/cancel")->assertOk();

        $this->assertSame('cancelled', $event->fresh()->status);
        Mail::assertQueued(EventCancelledMail::class, fn ($mail) => $mail->hasTo($r1->email));
        Mail::assertQueued(EventCancelledMail::class, fn ($mail) => $mail->hasTo($r2->email));
        $publicEvents = $this->getJson('/api/events')->json();
        $this->assertFalse(collect($publicEvents)->contains('slug', 'live-event'));
    }

    public function test_only_a_pending_or_approved_event_can_be_cancelled(): void
    {
        $owner = $this->makeUser();
        $org = $this->makeOrganization($owner);
        $draft = Event::create(['title' => 'Draft Event', 'status' => 'draft', 'organizer_id' => $owner->id, 'organization_id' => $org->id, 'slug' => 'draft-event']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/events/{$draft->id}/cancel")->assertStatus(422);
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_a_legacy_event_with_no_owner_can_be_edited_by_anyone(): void
    {
        Sanctum::actingAs($this->makeUser());
        $event = Event::create(['title' => 'Legacy Event', 'status' => 'pending', 'organizer_id' => null, 'slug' => 'legacy-event', 'capacity' => 10]);

        $this->putJson("/api/events/{$event->id}", ['capacity' => 50])->assertOk();
    }

    public function test_anyone_can_duplicate_someone_elses_event(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $other = $this->makeUser('other@example.com');
        $event = Event::create(['title' => 'Original', 'status' => 'approved', 'organizer_id' => $owner->id, 'slug' => 'original']);

        Sanctum::actingAs($other);
        $response = $this->postJson("/api/events/{$event->id}/duplicate")->assertCreated();

        $this->assertSame($other->id, $response->json('organizerId'));
        $this->assertSame('draft', $response->json('status'));
    }

    public function test_the_owner_can_mark_an_approved_event_completed(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $event = Event::create(['title' => 'Wrapped Up', 'status' => 'approved', 'organizer_id' => $owner->id, 'slug' => 'wrapped-up']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/events/{$event->id}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    public function test_a_stranger_cannot_mark_someone_elses_event_completed(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $stranger = $this->makeUser('stranger@example.com');
        $org = $this->makeOrganization($owner);
        $event = Event::create(['title' => 'Owned Event', 'status' => 'approved', 'organizer_id' => $owner->id, 'organization_id' => $org->id, 'slug' => 'owned-event']);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/events/{$event->id}/complete")->assertForbidden();
        $this->assertSame('approved', $event->fresh()->status);
    }

    public function test_a_pending_event_cannot_be_marked_completed(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $event = Event::create(['title' => 'Not Yet Approved', 'status' => 'pending', 'organizer_id' => $owner->id, 'slug' => 'not-yet-approved']);

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
        $event = Event::create(['title' => 'Pending Event', 'status' => 'pending', 'organizer_id' => $organizer->id, 'slug' => 'pending-event']);

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

    public function test_approving_and_rejecting_record_who_decided_and_when(): void
    {
        $organizer = $this->makeUser('organizer@example.com');
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $toApprove = Event::create(['title' => 'A', 'status' => 'pending', 'organizer_id' => $organizer->id, 'slug' => 'a-event']);
        $toReject = Event::create(['title' => 'B', 'status' => 'pending', 'organizer_id' => $organizer->id, 'slug' => 'b-event']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$toApprove->id}/approve")->assertOk();
        $this->postJson("/api/events/{$toReject->id}/reject")->assertOk();

        $approved = $toApprove->fresh();
        $this->assertSame('approved', $approved->review_decision);
        $this->assertSame($admin->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);

        $rejected = $toReject->fresh();
        $this->assertSame('rejected', $rejected->review_decision);
        $this->assertSame($admin->id, $rejected->reviewed_by);
    }

    public function test_approving_an_event_emails_its_organizer_with_a_link_to_it(): void
    {
        Mail::fake();
        $organizer = $this->makeUser('organizer@example.com');
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $event = Event::create(['title' => 'Tech Meetup', 'status' => 'pending', 'organizer_id' => $organizer->id, 'slug' => 'tech-meetup', 'venue' => 'Innovation Hub', 'date' => '2026-10-10']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$event->id}/approve")->assertOk();

        Mail::assertQueued(\App\Mail\ApplicationDecisionMail::class, fn ($mail) => $mail->hasTo('organizer@example.com')
            && $mail->approved === true
            && $mail->applicationFor === 'event "Tech Meetup"'
            && str_ends_with($mail->actionUrl, '/events/tech-meetup')
            && $mail->details['Venue'] === 'Innovation Hub'
            && $mail->details['Date'] === 'October 10, 2026');
    }

    public function test_rejecting_an_event_emails_its_organizer_without_a_link(): void
    {
        Mail::fake();
        $organizer = $this->makeUser('organizer@example.com');
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $event = Event::create(['title' => 'Tech Meetup', 'status' => 'pending', 'organizer_id' => $organizer->id, 'slug' => 'tech-meetup']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$event->id}/reject")->assertOk();

        Mail::assertQueued(\App\Mail\ApplicationDecisionMail::class, fn ($mail) => $mail->hasTo('organizer@example.com') && $mail->approved === false);

        (new \App\Mail\ApplicationDecisionMail('Ana', 'event "Tech Meetup"', false, [], 'https://example.test/events/tech-meetup', 'View your event'))
            ->assertSeeInHtml("We weren't able to approve", false)
            ->assertDontSeeInHtml('View your event');
    }

    public function test_rejecting_an_event_with_a_reason_stores_it_and_emails_it(): void
    {
        Mail::fake();
        $organizer = $this->makeUser('organizer@example.com');
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $event = Event::create(['title' => 'Tech Meetup', 'status' => 'pending', 'organizer_id' => $organizer->id, 'slug' => 'tech-meetup']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$event->id}/reject", ['reason' => 'The venue is not confirmed.'])
            ->assertOk()
            ->assertJsonPath('rejectionReason', 'The venue is not confirmed.');

        $this->assertSame('The venue is not confirmed.', $event->fresh()->rejection_reason);
        Mail::assertQueued(\App\Mail\ApplicationDecisionMail::class, fn ($mail) => $mail->reason === 'The venue is not confirmed.');
    }

    /** show() returns any event by slug regardless of status - the reason must not be readable there. */
    public function test_the_rejection_reason_never_appears_on_the_public_event_page(): void
    {
        $organizer = $this->makeUser('organizer@example.com');
        $event = Event::create(['title' => 'Nope', 'status' => 'rejected', 'organizer_id' => $organizer->id, 'slug' => 'nope-x']);
        $event->forceFill(['review_decision' => 'rejected', 'rejection_reason' => 'Private feedback for the organizer'])->save();

        $this->assertArrayNotHasKey('rejectionReason', $this->getJson('/api/events/nope-x')->assertOk()->json());
    }

    public function test_the_organizer_and_the_admin_can_read_the_rejection_reason_in_their_event_list(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $org = $this->makeOrganization($owner);
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $event = Event::create(['title' => 'Nope', 'status' => 'rejected', 'organizer_id' => $owner->id, 'organization_id' => $org->id, 'slug' => 'nope-y']);
        $event->forceFill(['review_decision' => 'rejected', 'rejection_reason' => 'Needs a confirmed venue'])->save();

        Sanctum::actingAs($owner);
        $this->assertSame('Needs a confirmed venue', $this->getJson('/api/admin/events')->assertOk()->json('0.rejectionReason'));

        Sanctum::actingAs($admin);
        $this->assertSame('Needs a confirmed venue', $this->getJson('/api/admin/events')->assertOk()->json('0.rejectionReason'));
    }

    public function test_approving_an_event_clears_any_earlier_rejection_reason(): void
    {
        Mail::fake();
        $organizer = $this->makeUser('organizer@example.com');
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $event = Event::create(['title' => 'Tech Meetup', 'status' => 'pending', 'organizer_id' => $organizer->id, 'slug' => 'tech-meetup']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$event->id}/reject", ['reason' => 'Try again'])->assertOk();
        $this->postJson("/api/events/{$event->id}/approve")->assertOk();

        $this->assertNull($event->fresh()->rejection_reason);
    }

    public function test_the_approval_link_for_a_private_event_carries_its_access_token(): void
    {
        Mail::fake();
        $organizer = $this->makeUser('organizer@example.com');
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $event = Event::create(['title' => 'Secret', 'status' => 'pending', 'organizer_id' => $organizer->id, 'slug' => 'secret', 'is_private' => true, 'private_link' => 'abc123']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$event->id}/approve")->assertOk();

        Mail::assertQueued(\App\Mail\ApplicationDecisionMail::class, fn ($mail) => str_ends_with($mail->actionUrl, '/events/secret?access=abc123'));
    }

    public function test_an_admin_deciding_on_their_own_event_is_not_emailed(): void
    {
        Mail::fake();
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $event = Event::create(['title' => 'Mine', 'status' => 'pending', 'organizer_id' => $admin->id, 'slug' => 'mine-x']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$event->id}/approve")->assertOk();

        Mail::assertNotQueued(\App\Mail\ApplicationDecisionMail::class);
    }

    public function test_a_legacy_event_with_no_organizer_is_decided_without_emailing_anyone(): void
    {
        Mail::fake();
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $event = Event::create(['title' => 'Legacy', 'status' => 'pending', 'slug' => 'legacy-x']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$event->id}/approve")->assertOk();

        Mail::assertNotQueued(\App\Mail\ApplicationDecisionMail::class);
    }

    public function test_approving_the_same_event_twice_only_emails_once(): void
    {
        Mail::fake();
        $organizer = $this->makeUser('organizer@example.com');
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $event = Event::create(['title' => 'Tech Meetup', 'status' => 'pending', 'organizer_id' => $organizer->id, 'slug' => 'tech-meetup']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$event->id}/approve")->assertOk();
        $this->postJson("/api/events/{$event->id}/approve")->assertOk();

        Mail::assertQueued(\App\Mail\ApplicationDecisionMail::class, 1);
    }

    /** status moves on (approved -> completed), but the fact it was approved must not disappear from the history. */
    public function test_an_approval_stays_in_the_history_after_the_event_completes(): void
    {
        $organizer = $this->makeUser('organizer@example.com');
        $org = $this->makeOrganization($organizer);
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        $event = Event::create(['title' => 'Old News', 'status' => 'pending', 'organizer_id' => $organizer->id, 'organization_id' => $org->id, 'slug' => 'old-news']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/events/{$event->id}/approve")->assertOk();
        $this->postJson("/api/events/{$event->id}/complete")->assertOk();

        $listed = collect($this->getJson('/api/admin/events')->assertOk()->json())->firstWhere('id', $event->id);
        $this->assertSame('completed', $listed['status']);
        $this->assertSame('approved', $listed['reviewDecision']);
        $this->assertSame($admin->id, $listed['reviewedBy']);
        $this->assertNotNull($listed['reviewedAt']);
    }

    public function test_the_admin_event_list_carries_the_organizers_contact_details_for_vetting(): void
    {
        $organizer = $this->makeUser('organizer@example.com');
        $organizer->forceFill(['contact_number' => '0917 123 4567'])->save();
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        Event::create(['title' => 'Vet Me', 'status' => 'pending', 'organizer_id' => $organizer->id, 'slug' => 'vet-me']);

        Sanctum::actingAs($admin);
        $listed = $this->getJson('/api/admin/events')->assertOk()->json('0');

        $this->assertSame('organizer@example.com', $listed['organizer']['email']);
        $this->assertSame('0917 123 4567', $listed['organizer']['contactNumber']);
    }

    public function test_a_non_admin_never_gets_a_co_members_email_or_number_in_the_event_list(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $owner->forceFill(['contact_number' => '0917 123 4567'])->save();
        $coMember = $this->makeUser('comember@example.com');
        $org = $this->makeOrganization($owner);
        $org->members()->attach($coMember->id, ['role' => 'member']);
        Event::create(['title' => 'Club Event', 'status' => 'approved', 'organizer_id' => $owner->id, 'organization_id' => $org->id, 'slug' => 'club-event-x']);

        Sanctum::actingAs($coMember);
        $listed = $this->getJson('/api/admin/events')->assertOk()->json('0');

        $this->assertSame('Organizer', $listed['organizer']['name']);
        $this->assertArrayNotHasKey('email', $listed['organizer']);
        $this->assertArrayNotHasKey('contactNumber', $listed['organizer']);
    }

    public function test_the_public_event_page_shows_who_actually_created_it_without_exposing_their_contact_details(): void
    {
        $organizer = $this->makeUser('organizer@example.com');
        $organizer->forceFill(['contact_number' => '0917 123 4567', 'institution' => 'Acme University'])->save();
        Event::create(['title' => 'Public One', 'status' => 'approved', 'organizer_id' => $organizer->id, 'slug' => 'public-one']);

        $event = $this->getJson('/api/events/public-one')->assertOk()->json();

        $this->assertSame('Organizer', $event['organizer']['name']);
        $this->assertSame('Acme University', $event['organizer']['institution']);
        $this->assertArrayNotHasKey('email', $event['organizer']);
        $this->assertArrayNotHasKey('contactNumber', $event['organizer']);
        $this->assertArrayNotHasKey('password', $event['organizer']);
    }

    public function test_the_public_listing_carries_the_real_organizer_name_for_each_event(): void
    {
        $organizer = $this->makeUser('organizer@example.com');
        Event::create(['title' => 'Listed', 'status' => 'approved', 'is_private' => false, 'organizer_id' => $organizer->id, 'slug' => 'listed']);

        $listed = $this->getJson('/api/events')->assertOk()->json('0');

        $this->assertSame('Organizer', $listed['organizer']['name']);
        $this->assertArrayNotHasKey('email', $listed['organizer']);
    }

    public function test_the_admin_event_list_is_ordered_by_event_date_newest_first(): void
    {
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        Event::create(['title' => 'Oldest', 'status' => 'approved', 'slug' => 'oldest', 'date' => '2026-01-10', 'start_time' => '09:00']);
        Event::create(['title' => 'Newest', 'status' => 'approved', 'slug' => 'newest', 'date' => '2026-12-10', 'start_time' => '09:00']);
        Event::create(['title' => 'Middle', 'status' => 'approved', 'slug' => 'middle', 'date' => '2026-06-10', 'start_time' => '09:00']);

        Sanctum::actingAs($admin);
        $titles = collect($this->getJson('/api/admin/events')->assertOk()->json())->pluck('title')->all();

        $this->assertSame(['Newest', 'Middle', 'Oldest'], $titles);
    }

    public function test_submitting_an_event_acknowledges_the_application_to_its_organizer(): void
    {
        Mail::fake();
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($user = $this->makeUser('organizer@example.com'));
        $event = Event::create([
            'title' => 'Tech Meetup', 'status' => 'draft', 'organizer_id' => $user->id, 'slug' => 'tech-meetup',
            'type' => 'Meetup', 'venue' => 'Venue', 'date' => '2026-08-01', 'start_time' => '10:00', 'end_time' => '12:00', 'capacity' => 30,
        ]);

        $this->postJson("/api/events/{$event->id}/submit")->assertOk();

        Mail::assertQueued(\App\Mail\ApplicationReceivedMail::class, fn ($mail) => $mail->hasTo('organizer@example.com') && $mail->applicationFor === 'event "Tech Meetup"');
    }

    public function test_an_admin_submitting_their_own_event_is_not_sent_an_acknowledgment(): void
    {
        Mail::fake();
        $admin = $this->makeUser('admin@example.com');
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);
        $event = Event::create([
            'title' => 'Admin Event', 'status' => 'draft', 'organizer_id' => $admin->id, 'slug' => 'admin-event',
            'type' => 'Meetup', 'venue' => 'Venue', 'date' => '2026-08-01', 'start_time' => '10:00', 'end_time' => '12:00', 'capacity' => 30,
        ]);

        $this->postJson("/api/events/{$event->id}/submit")->assertOk();

        Mail::assertNotQueued(\App\Mail\ApplicationReceivedMail::class);
    }
}

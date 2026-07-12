<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'title' => 'Test Event', 'status' => 'approved', 'slug' => 'test-event-'.uniqid(),
            'type' => 'Meetup', 'venue' => 'Venue', 'date' => '2026-08-01',
            'start_time' => '10:00', 'end_time' => '12:00', 'capacity' => 0, 'allow_walk_ins' => true,
        ], $overrides));
    }

    public function test_a_registration_can_be_fetched_publicly_by_its_own_id_with_live_event_status(): void
    {
        $event = $this->makeEvent(['status' => 'approved']);
        $registration = $event->registrations()->create(['name' => 'Attendee', 'email' => 'attendee@example.com', 'qr_code' => 'QR-1', 'attended' => false]);

        $response = $this->getJson("/api/registrations/{$registration->id}")->assertOk();
        $this->assertSame('approved', $response->json('event.status'));
        $this->assertFalse($response->json('attended'));

        $event->update(['status' => 'completed']);
        $registration->update(['attended' => true]);

        $response = $this->getJson("/api/registrations/{$registration->id}")->assertOk();
        $this->assertSame('completed', $response->json('event.status'));
        $this->assertTrue($response->json('attended'));
    }

    public function test_the_public_registration_lookup_does_not_leak_contact_or_payment_details(): void
    {
        // This is a plain sequential id, not a token - anyone can iterate
        // it, so the response has to stay limited to what the Pass page
        // actually renders. Locks in the fix for a real leak (email,
        // custom form answers, payment ref/screenshot were all exposed).
        $event = $this->makeEvent(['status' => 'approved']);
        $registration = $event->registrations()->create([
            'name' => 'Attendee', 'email' => 'secret@example.com', 'qr_code' => 'QR-2',
            'custom_data' => ['phone' => '555-1234'], 'payment_ref' => 'REF-999', 'payment_status' => 'pending',
        ]);

        $response = $this->getJson("/api/registrations/{$registration->id}")->assertOk();

        $response->assertJsonMissingPath('email');
        $response->assertJsonMissingPath('customData');
        $response->assertJsonMissingPath('paymentRef');
        $response->assertJsonMissingPath('paymentStatus');
        $response->assertJsonMissingPath('paymentScreenshotUrl');
    }

    public function test_registering_for_an_event_requires_authentication(): void
    {
        Mail::fake();
        $event = $this->makeEvent();

        $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertUnauthorized();
    }

    public function test_an_authenticated_user_can_register_and_the_registration_is_tied_to_their_account(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $user = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password123')]);
        // email_verified_at isn't mass-assignable (see User::$fillable).
        $user->forceFill(['email_verified_at' => now()])->save();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertCreated();

        $this->assertSame($user->id, $response->json('userId'));
        Mail::assertQueued(\App\Mail\RegistrationConfirmedMail::class);
    }

    public function test_registering_twice_with_the_same_account_returns_the_existing_registration(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $user = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password123')]);
        $user->forceFill(['email_verified_at' => now()])->save();
        Sanctum::actingAs($user);

        $first = $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertCreated();

        $second = $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame($first->json('qrCode'), $second->json('qrCode'));
        $this->assertSame(1, Registration::where('event_id', $event->id)->where('user_id', $user->id)->count());
    }

    public function test_registration_confirmed_email_renders_with_the_logo(): void
    {
        $event = $this->makeEvent();
        $registration = $event->registrations()->create(['name' => 'Ana', 'email' => 'ana@example.com', 'qr_code' => 'QR-TEST-LOGO']);

        $html = (new \App\Mail\RegistrationConfirmedMail($registration))->render();

        $this->assertStringContainsString('/logo-email.png', $html);
    }

    public function test_an_unverified_account_cannot_register_for_an_event(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $unverified = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($unverified);

        $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertForbidden();
    }

    public function test_walk_in_check_in_does_not_require_authentication(): void
    {
        Mail::fake();
        $event = $this->makeEvent();

        $response = $this->postJson("/api/events/{$event->id}/walk-in", ['name' => 'Walk In', 'email' => 'walkin@example.com'])
            ->assertCreated();

        $this->assertTrue($response->json('attended'));
        $this->assertNull($response->json('userId'));
    }

    public function test_organizer_can_add_edit_and_remove_a_guest_by_hand(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        Sanctum::actingAs(User::create(['name' => 'Org', 'email' => 'org@example.com', 'password' => bcrypt('password123')]));

        $added = $this->postJson("/api/events/{$event->id}/registrations", ['name' => 'Manual Guest', 'email' => 'manual@example.com'])
            ->assertCreated();
        $id = $added->json('id');

        $this->putJson("/api/registrations/{$id}", ['name' => 'Renamed Guest', 'attended' => true])
            ->assertOk()
            ->assertJsonPath('name', 'Renamed Guest')
            ->assertJsonPath('attended', true);

        $this->deleteJson("/api/registrations/{$id}")->assertOk();
        $this->assertNull(Registration::find($id));
    }

    public function test_qr_code_sequence_does_not_collide_after_a_middle_registration_is_deleted(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        Sanctum::actingAs(User::create(['name' => 'Org', 'email' => 'org@example.com', 'password' => bcrypt('password123')]));

        $first = $this->postJson("/api/events/{$event->id}/registrations", ['name' => 'First', 'email' => 'first@example.com'])->assertCreated();
        $second = $this->postJson("/api/events/{$event->id}/registrations", ['name' => 'Second', 'email' => 'second@example.com'])->assertCreated();
        $this->postJson("/api/events/{$event->id}/registrations", ['name' => 'Third', 'email' => 'third@example.com'])->assertCreated();

        // Deleting the middle registration drops count() below the highest
        // sequence already issued (Third is still QR-E00X-P003).
        $this->deleteJson("/api/registrations/{$second->json('id')}")->assertOk();

        $fourth = $this->postJson("/api/events/{$event->id}/registrations", ['name' => 'Fourth', 'email' => 'fourth@example.com'])
            ->assertCreated();

        $this->assertNotSame($first->json('qrCode'), $fourth->json('qrCode'));
        $this->assertNotSame($second->json('qrCode'), $fourth->json('qrCode'));
        $this->assertSame(1, Registration::where('qr_code', $fourth->json('qrCode'))->count());
    }

    public function test_registrations_are_waitlisted_once_the_event_is_full(): void
    {
        Mail::fake();
        $event = $this->makeEvent(['capacity' => 1]);
        Sanctum::actingAs(User::create(['name' => 'Org', 'email' => 'org@example.com', 'password' => bcrypt('password123')]));

        $first = $this->postJson("/api/events/{$event->id}/registrations", ['name' => 'First', 'email' => 'first@example.com'])->assertCreated();
        $second = $this->postJson("/api/events/{$event->id}/registrations", ['name' => 'Second', 'email' => 'second@example.com'])->assertCreated();

        $this->assertFalse($first->json('waitlisted'));
        $this->assertTrue($second->json('waitlisted'));

        $this->postJson("/api/registrations/{$second->json('id')}/promote")
            ->assertOk()
            ->assertJsonPath('waitlisted', false);
    }

    public function test_walk_ins_bypass_the_waitlist(): void
    {
        Mail::fake();
        $event = $this->makeEvent(['capacity' => 1]);
        $this->postJson("/api/events/{$event->id}/walk-in", ['name' => 'First', 'email' => 'first@example.com'])->assertCreated();

        $response = $this->postJson("/api/events/{$event->id}/walk-in", ['name' => 'Second', 'email' => 'second@example.com'])
            ->assertCreated();

        $this->assertFalse($response->json('waitlisted'));
    }

    public function test_csv_import_skips_invalid_and_duplicate_rows_and_reports_why(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        Sanctum::actingAs(User::create(['name' => 'Org', 'email' => 'org@example.com', 'password' => bcrypt('password123')]));

        $event->registrations()->create(['name' => 'Existing', 'email' => 'existing@example.com', 'qr_code' => 'QR-EXISTING']);

        $csv = "name,email\nNew Person,new@example.com\n,noname@example.com\nBad,not-an-email\nExisting,existing@example.com\n";
        $file = UploadedFile::fake()->createWithContent('guests.csv', $csv);

        $response = $this->postJson("/api/events/{$event->id}/registrations/import", ['file' => $file])
            ->assertOk();

        $this->assertSame(1, $response->json('imported'));
        $this->assertSame(3, $response->json('skipped'));
    }
}

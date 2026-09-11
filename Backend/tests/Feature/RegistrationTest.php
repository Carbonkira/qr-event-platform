<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Registration;
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

    private function makeUser(string $email = 'organizer@example.com'): Organizer
    {
        $organizer = Organizer::create(['name' => 'Organizer', 'email' => $email, 'password' => bcrypt('password123')]);
        // Neither is mass-assignable (see Organizer::$fillable).
        $organizer->forceFill(['email_verified_at' => now(), 'approval_status' => 'approved'])->save();

        return $organizer;
    }

    private function makeParticipant(string $email = 'participant@example.com', bool $verified = true): Participant
    {
        $participant = Participant::create(['name' => 'Ana', 'email' => $email, 'password' => bcrypt('password123')]);
        if ($verified) {
            $participant->forceFill(['email_verified_at' => now()])->save();
        }

        return $participant;
    }

    private function makeOrganization(Organizer $owner): Organization
    {
        $org = Organization::create(['name' => "{$owner->name}'s Org", 'slug' => 'org-'.uniqid()]);
        $org->members()->attach($owner->id, ['role' => 'owner']);

        return $org;
    }

    public function test_a_registration_can_be_fetched_publicly_by_its_own_id_with_live_event_status(): void
    {
        $event = $this->makeEvent(['status' => 'approved']);
        $registration = $event->registrations()->create(['name' => 'Attendee', 'email' => 'attendee@example.com', 'qr_code' => 'QR-1', 'attended' => false]);

        $response = $this->getJson("/api/registrations/{$registration->id}?token={$registration->pass_token}")->assertOk();
        $this->assertSame('approved', $response->json('event.status'));
        $this->assertFalse($response->json('attended'));

        $event->update(['status' => 'completed']);
        $registration->update(['attended' => true]);

        $response = $this->getJson("/api/registrations/{$registration->id}?token={$registration->pass_token}")->assertOk();
        $this->assertSame('completed', $response->json('event.status'));
        $this->assertTrue($response->json('attended'));
    }

    public function test_the_registration_endpoint_requires_the_correct_pass_token(): void
    {
        // The id alone is a plain sequential integer - anyone can iterate
        // it, so the pass_token in the URL is the actual credential (see
        // RegistrationController::show()). No token, or the wrong one, must
        // 404 rather than reveal that the id even exists.
        $event = $this->makeEvent(['status' => 'approved']);
        $registration = $event->registrations()->create(['name' => 'Attendee', 'email' => 'attendee@example.com', 'qr_code' => 'QR-TOK']);

        $this->getJson("/api/registrations/{$registration->id}")->assertNotFound();
        $this->getJson("/api/registrations/{$registration->id}?token=wrong-token")->assertNotFound();
        $this->getJson("/api/registrations/{$registration->id}?token={$registration->pass_token}")->assertOk();
    }

    public function test_a_registration_created_before_pass_tokens_existed_is_grandfathered_to_no_token_required(): void
    {
        $event = $this->makeEvent(['status' => 'approved']);
        $registration = $event->registrations()->create(['name' => 'Attendee', 'email' => 'attendee@example.com', 'qr_code' => 'QR-LEGACY']);
        $registration->forceFill(['pass_token' => null])->save();

        $this->getJson("/api/registrations/{$registration->id}")->assertOk();
    }

    public function test_the_public_registration_lookup_does_not_leak_contact_or_payment_details(): void
    {
        // This is a plain sequential id, not a token - anyone can iterate
        // it, so the response has to stay limited to what the Pass page
        // actually renders. Locks in the fix for a real leak (email,
        // custom form answers, payment ref/screenshot were all exposed).
        // paymentStatus itself is intentionally exposed (unlike the rest) -
        // the Pass page needs it to render the "payment under review" state.
        $event = $this->makeEvent(['status' => 'approved']);
        $registration = $event->registrations()->create([
            'name' => 'Attendee', 'email' => 'secret@example.com', 'qr_code' => 'QR-2',
            'custom_data' => ['phone' => '555-1234'], 'payment_ref' => 'REF-999', 'payment_status' => 'pending',
        ]);

        $response = $this->getJson("/api/registrations/{$registration->id}?token={$registration->pass_token}")->assertOk();

        $response->assertJsonMissingPath('email');
        $response->assertJsonMissingPath('customData');
        $response->assertJsonMissingPath('paymentRef');
        $response->assertJsonMissingPath('paymentScreenshotUrl');
        $this->assertSame('pending', $response->json('paymentStatus'));
    }

    public function test_qr_pass_is_withheld_until_payment_is_verified(): void
    {
        Mail::fake();
        $organizer = $this->makeUser();
        $org = $this->makeOrganization($organizer);
        $event = $this->makeEvent(['status' => 'approved', 'pricing' => 'paid', 'price' => 500, 'organization_id' => $org->id]);
        $registration = $event->registrations()->create([
            'name' => 'Attendee', 'email' => 'attendee@example.com', 'qr_code' => 'QR-PAID-1',
            'payment_ref' => 'REF-1', 'payment_status' => 'pending',
        ]);
        $token = $registration->pass_token;

        $pending = $this->getJson("/api/registrations/{$registration->id}?token={$token}")->assertOk();
        $this->assertNull($pending->json('qrCode'));
        $this->getJson("/api/registrations/{$registration->id}/qr.png?token={$token}")->assertForbidden();

        Sanctum::actingAs($organizer);
        $this->postJson("/api/registrations/{$registration->id}/verify-payment", ['approved' => true])->assertOk();
        Mail::assertQueued(\App\Mail\PaymentVerifiedMail::class);

        $verified = $this->getJson("/api/registrations/{$registration->id}?token={$token}")->assertOk();
        $this->assertSame('QR-PAID-1', $verified->json('qrCode'));
        $this->getJson("/api/registrations/{$registration->id}/qr.png?token={$token}")->assertOk();
    }

    public function test_rejecting_a_payment_queues_the_rejection_email_and_keeps_the_qr_withheld(): void
    {
        Mail::fake();
        $organizer = $this->makeUser();
        $org = $this->makeOrganization($organizer);
        $event = $this->makeEvent(['status' => 'approved', 'pricing' => 'paid', 'price' => 500, 'organization_id' => $org->id]);
        $registration = $event->registrations()->create([
            'name' => 'Attendee', 'email' => 'attendee@example.com', 'qr_code' => 'QR-PAID-2',
            'payment_ref' => 'REF-2', 'payment_status' => 'pending',
        ]);
        $token = $registration->pass_token;

        Sanctum::actingAs($organizer);
        $this->postJson("/api/registrations/{$registration->id}/verify-payment", ['approved' => false])->assertOk();
        Mail::assertQueued(\App\Mail\PaymentRejectedMail::class);

        $rejected = $this->getJson("/api/registrations/{$registration->id}?token={$token}")->assertOk();
        $this->assertNull($rejected->json('qrCode'));
        $this->getJson("/api/registrations/{$registration->id}/qr.png?token={$token}")->assertForbidden();
    }

    public function test_a_stranger_cannot_verify_payment_for_someone_elses_event(): void
    {
        // Without this, the registrant who owns the registration (or anyone
        // else with an account) could call verify-payment themselves and
        // unlock their own gated QR pass without actually paying.
        Mail::fake();
        $organizer = $this->makeUser('owner@example.com');
        $org = $this->makeOrganization($organizer);
        $stranger = $this->makeUser('stranger@example.com');
        $event = $this->makeEvent(['status' => 'approved', 'pricing' => 'paid', 'price' => 500, 'organization_id' => $org->id]);
        $registration = $event->registrations()->create([
            'name' => 'Attendee', 'email' => 'attendee@example.com', 'qr_code' => 'QR-PAID-3',
            'payment_ref' => 'REF-3', 'payment_status' => 'pending',
        ]);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/registrations/{$registration->id}/verify-payment", ['approved' => true])->assertForbidden();
        Mail::assertNotQueued(\App\Mail\PaymentVerifiedMail::class);
    }

    public function test_registering_for_an_event_requires_authentication(): void
    {
        Mail::fake();
        $event = $this->makeEvent();

        $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertUnauthorized();
    }

    /** Registering for an event is strictly a Participant action - see EnsureParticipant. */
    public function test_an_organizer_account_cannot_register_for_an_event(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        Sanctum::actingAs($this->makeUser());

        $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertForbidden();
    }

    public function test_an_authenticated_participant_can_register_and_the_registration_is_tied_to_their_account(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $participant = $this->makeParticipant('ana@example.com');
        Sanctum::actingAs($participant);

        $response = $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertCreated();

        $this->assertSame($participant->id, $response->json('participantId'));
        Mail::assertQueued(\App\Mail\RegistrationConfirmedMail::class);
    }

    public function test_registering_twice_with_the_same_account_returns_the_existing_registration(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $participant = $this->makeParticipant('ana@example.com');
        Sanctum::actingAs($participant);

        $first = $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertCreated();

        $second = $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame($first->json('qrCode'), $second->json('qrCode'));
        $this->assertSame(1, Registration::where('event_id', $event->id)->where('participant_id', $participant->id)->count());
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
        $unverified = $this->makeParticipant('ana@example.com', verified: false);
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
        $this->assertNull($response->json('participantId'));
    }

    public function test_organizer_can_add_edit_and_remove_a_guest_by_hand(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        Sanctum::actingAs($this->makeUser('org@example.com'));

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
        Sanctum::actingAs($this->makeUser('org@example.com'));

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
        Sanctum::actingAs($this->makeUser('org@example.com'));

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
        Sanctum::actingAs($this->makeUser('org@example.com'));

        $event->registrations()->create(['name' => 'Existing', 'email' => 'existing@example.com', 'qr_code' => 'QR-EXISTING']);

        $csv = "name,email\nNew Person,new@example.com\n,noname@example.com\nBad,not-an-email\nExisting,existing@example.com\n";
        $file = UploadedFile::fake()->createWithContent('guests.csv', $csv);

        $response = $this->postJson("/api/events/{$event->id}/registrations/import", ['file' => $file])
            ->assertOk();

        $this->assertSame(1, $response->json('imported'));
        $this->assertSame(3, $response->json('skipped'));
    }
}

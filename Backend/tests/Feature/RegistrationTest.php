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
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/events/{$event->id}/register", ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertCreated();

        $this->assertSame($user->id, $response->json('userId'));
        Mail::assertQueued(\App\Mail\RegistrationConfirmedMail::class);
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

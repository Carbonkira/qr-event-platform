<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrganizer(string $email, bool $admin = false): Organizer
    {
        $organizer = Organizer::create(['name' => 'Organizer', 'email' => $email, 'password' => bcrypt('password123')]);
        // None of these are mass-assignable (see Organizer::$fillable).
        $organizer->forceFill(['email_verified_at' => now(), 'approval_status' => 'approved', 'role' => $admin ? 'admin' : 'organizer'])->save();

        return $organizer;
    }

    /** An organization with one member, plus an event under it with one registration. */
    private function makeEventWithRegistration(Organizer $member, string $qrCode = 'QR-E001-P001-ABCDEFGH', ?bool $withOrganization = true): Registration
    {
        $organizationId = null;
        if ($withOrganization) {
            $org = Organization::create(['name' => 'Org '.uniqid(), 'slug' => 'org-'.uniqid()]);
            $org->members()->attach($member->id, ['role' => 'member']);
            $organizationId = $org->id;
        }

        $event = Event::create([
            'title' => 'Scan Test', 'status' => 'approved', 'slug' => 'scan-test-'.uniqid(),
            'organization_id' => $organizationId, 'capacity' => 0,
        ]);

        return $event->registrations()->create(['name' => 'Attendee', 'email' => 'attendee@example.com', 'qr_code' => $qrCode]);
    }

    public function test_a_member_of_the_events_organization_can_check_someone_in(): void
    {
        $member = $this->makeOrganizer('member@example.com');
        $registration = $this->makeEventWithRegistration($member);

        Sanctum::actingAs($member);
        $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E001-P001-ABCDEFGH'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($registration->fresh()->attended);
        $this->assertNotNull($registration->fresh()->check_in_time);
    }

    public function test_scanning_the_same_code_twice_reports_a_duplicate(): void
    {
        $member = $this->makeOrganizer('member@example.com');
        $this->makeEventWithRegistration($member);

        Sanctum::actingAs($member);
        $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E001-P001-ABCDEFGH'])->assertJsonPath('success', true);
        $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E001-P001-ABCDEFGH'])
            ->assertJsonPath('success', false)
            ->assertJsonPath('type', 'duplicate');
    }

    public function test_an_unknown_code_is_not_found(): void
    {
        Sanctum::actingAs($this->makeOrganizer('member@example.com'));

        $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E999-P999-NOPENOPE'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('type', 'not_found');
    }

    /** Before, any approved organizer could check anyone into any organization's event just by presenting the code. */
    public function test_an_organizer_cannot_check_in_someone_for_an_event_they_do_not_manage(): void
    {
        $owner = $this->makeOrganizer('owner@example.com');
        $stranger = $this->makeOrganizer('stranger@example.com');
        $registration = $this->makeEventWithRegistration($owner);
        // The stranger belongs to a different organization entirely.
        $otherOrg = Organization::create(['name' => 'Other', 'slug' => 'other-org']);
        $otherOrg->members()->attach($stranger->id, ['role' => 'owner']);

        Sanctum::actingAs($stranger);
        $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E001-P001-ABCDEFGH'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('type', 'not_found');

        $this->assertFalse($registration->fresh()->attended);
    }

    public function test_a_code_for_an_event_you_do_not_manage_looks_exactly_like_an_unknown_one(): void
    {
        $owner = $this->makeOrganizer('owner@example.com');
        $stranger = $this->makeOrganizer('stranger@example.com');
        $this->makeEventWithRegistration($owner);

        Sanctum::actingAs($stranger);
        $real = $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E001-P001-ABCDEFGH'])->json();
        $fake = $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E999-P999-NOPENOPE'])->json();

        // Scanning must not be usable to discover which codes exist.
        $this->assertSame($fake, $real);
    }

    public function test_an_admin_can_check_someone_in_for_any_event(): void
    {
        $owner = $this->makeOrganizer('owner@example.com');
        $registration = $this->makeEventWithRegistration($owner);

        Sanctum::actingAs($this->makeOrganizer('admin@example.com', admin: true));
        $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E001-P001-ABCDEFGH'])->assertJsonPath('success', true);

        $this->assertTrue($registration->fresh()->attended);
    }

    public function test_an_event_with_no_organization_can_be_scanned_by_any_organizer(): void
    {
        // Same "legacy events stay open" carve-out the rest of the app has.
        $someone = $this->makeOrganizer('someone@example.com');
        $registration = $this->makeEventWithRegistration($someone, withOrganization: false);

        Sanctum::actingAs($this->makeOrganizer('other@example.com'));
        $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E001-P001-ABCDEFGH'])->assertJsonPath('success', true);

        $this->assertTrue($registration->fresh()->attended);
    }

    public function test_a_code_typed_by_hand_in_lowercase_with_stray_spaces_still_matches(): void
    {
        $member = $this->makeOrganizer('member@example.com');
        $registration = $this->makeEventWithRegistration($member);

        Sanctum::actingAs($member);
        $this->postJson('/api/attendance/scan', ['qrCode' => '  qr-e001-p001-abcdefgh '])->assertJsonPath('success', true);

        $this->assertTrue($registration->fresh()->attended);
    }

    public function test_a_code_issued_before_the_random_part_existed_still_works(): void
    {
        $member = $this->makeOrganizer('member@example.com');
        $registration = $this->makeEventWithRegistration($member, 'QR-E001-P001');

        Sanctum::actingAs($member);
        $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E001-P001'])->assertJsonPath('success', true);

        $this->assertTrue($registration->fresh()->attended);
    }

    public function test_a_participant_account_cannot_scan(): void
    {
        $participant = Participant::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password123')]);
        $participant->forceFill(['email_verified_at' => now()])->save();

        Sanctum::actingAs($participant);
        $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E001-P001-ABCDEFGH'])->assertForbidden();
    }

    public function test_scanning_requires_authentication(): void
    {
        $this->postJson('/api/attendance/scan', ['qrCode' => 'QR-E001-P001-ABCDEFGH'])->assertUnauthorized();
    }
}

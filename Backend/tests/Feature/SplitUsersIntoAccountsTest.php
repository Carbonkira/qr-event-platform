<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises the actual one-time cutover command against a realistic
 * pre-migration `users` table (built by hand via DB::table(), matching what
 * a real pre-split production database looks like) rather than through the
 * Organizer/Participant models, which don't have a `users`-table concept at
 * all - this is the one place that boundary still matters.
 */
class SplitUsersIntoAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserRow(string $email, array $overrides = []): int
    {
        return DB::table('users')->insertGetId(array_merge([
            'name' => 'User '.$email, 'email' => $email, 'password' => bcrypt('password123'),
            'role' => 'organizer', 'approval_status' => 'approved',
            'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    public function test_dry_run_reports_counts_and_writes_nothing(): void
    {
        $this->makeUserRow('organizer@example.com');
        $organizationId = DB::table('organizations')->insertGetId(['name' => 'Acme', 'slug' => 'acme', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('organization_members')->insert(['organization_id' => $organizationId, 'user_id' => 1, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('accounts:split-users', ['--dry-run' => true])
            ->expectsOutputToContain('Organizer only: 1')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('organizers')->count());
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_splits_organizer_only_participant_only_and_dual_signal_accounts_and_remaps_every_foreign_key(): void
    {
        $organizerOnlyId = $this->makeUserRow('org-only@example.com');
        $participantOnlyId = $this->makeUserRow('participant-only@example.com');
        $dualId = $this->makeUserRow('dual@example.com');
        $blankId = $this->makeUserRow('blank@example.com');

        $organizationId = DB::table('organizations')->insertGetId(['name' => 'Acme', 'slug' => 'acme', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('organization_members')->insert([
            ['organization_id' => $organizationId, 'user_id' => $organizerOnlyId, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $organizationId, 'user_id' => $dualId, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $eventId = DB::table('events')->insertGetId([
            'title' => 'Meetup', 'slug' => 'meetup', 'status' => 'approved', 'user_id' => $organizerOnlyId,
            'organization_id' => $organizationId, 'capacity' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $registrationId = DB::table('registrations')->insertGetId([
            'event_id' => $eventId, 'user_id' => $participantOnlyId, 'name' => 'P', 'email' => 'participant-only@example.com',
            'qr_code' => 'QR-1', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('registrations')->insert([
            'event_id' => $eventId, 'user_id' => $dualId, 'name' => 'D', 'email' => 'dual@example.com',
            'qr_code' => 'QR-2', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $inviteId = DB::table('organization_invites')->insertGetId([
            'organization_id' => $organizationId, 'email' => 'invitee@example.com', 'token' => 'tok-123',
            'invited_by' => $organizerOnlyId, 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Thread authored by the org member acting as organizer; reply from
        // the participant-only registrant - mirrors DiscussionController's
        // own "member OR registrant" rule, so the backfill has to check
        // membership per row rather than trusting a single global signal.
        $threadId = DB::table('discussion_threads')->insertGetId([
            'organization_id' => $organizationId, 'user_id' => $dualId, 'title' => 'Hi', 'body' => 'Hello',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $replyId = DB::table('discussion_replies')->insertGetId([
            'thread_id' => $threadId, 'user_id' => $participantOnlyId, 'body' => 'Hi back',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('accounts:split-users')
            ->expectsConfirmation('Proceed with the real migration? This renames `users` to `legacy_users_backup` when done.', 'yes')
            ->assertSuccessful();

        // users renamed, not dropped.
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('legacy_users_backup'));

        // Organizer-only and dual-signal both got an Organizer row; blank did not.
        $this->assertDatabaseHas('organizers', ['email' => 'org-only@example.com']);
        $this->assertDatabaseHas('organizers', ['email' => 'dual@example.com']);
        $this->assertDatabaseMissing('organizers', ['email' => 'participant-only@example.com']);
        $this->assertDatabaseMissing('organizers', ['email' => 'blank@example.com']);

        // Participant-only and dual-signal both got a Participant row; the
        // signal-less blank account defaults to Participant too.
        $this->assertDatabaseHas('participants', ['email' => 'participant-only@example.com']);
        $this->assertDatabaseHas('participants', ['email' => 'dual@example.com']);
        $this->assertDatabaseHas('participants', ['email' => 'blank@example.com']);
        $this->assertDatabaseMissing('participants', ['email' => 'org-only@example.com']);

        $newOrganizerId = DB::table('organizers')->where('email', 'org-only@example.com')->value('id');
        $newDualOrganizerId = DB::table('organizers')->where('email', 'dual@example.com')->value('id');
        $newParticipantId = DB::table('participants')->where('email', 'participant-only@example.com')->value('id');

        // Ids are NOT preserved from the old users.id (see the command's own
        // docblock on why) - ids assigned above are old ids, freshly
        // reassigned ones are looked up here rather than assumed equal.
        $this->assertSame($newOrganizerId, DB::table('events')->where('id', $eventId)->value('organizer_id'));
        $this->assertSame($newParticipantId, DB::table('registrations')->where('id', $registrationId)->value('participant_id'));
        $this->assertSame($newOrganizerId, DB::table('organization_invites')->where('id', $inviteId)->value('invited_by'));

        // Dual-signal user posted the thread while acting as an org member -
        // attributed to the organizer row, not the participant row.
        $thread = DB::table('discussion_threads')->where('id', $threadId)->first();
        $this->assertSame($newDualOrganizerId, $thread->organizer_user_id);
        $this->assertNull($thread->participant_user_id);

        // Participant-only user's reply is attributed to their participant row.
        $reply = DB::table('discussion_replies')->where('id', $replyId)->first();
        $this->assertSame($newParticipantId, $reply->participant_user_id);
        $this->assertNull($reply->organizer_user_id);
    }

    public function test_is_idempotent_by_refusing_to_run_twice(): void
    {
        $this->makeUserRow('solo@example.com');

        $this->artisan('accounts:split-users')
            ->expectsConfirmation('Proceed with the real migration? This renames `users` to `legacy_users_backup` when done.', 'yes')
            ->assertSuccessful();

        $this->artisan('accounts:split-users')->assertFailed();
    }
}

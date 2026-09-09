<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time (but --dry-run-able) cutover from the single `users` table to the
 * adviser-required separate `organizers`/`participants` tables. An account
 * that shows organizer signals (owns an event, is an org member, sent an
 * invite, or is an admin) gets an Organizer row; one that shows participant
 * signals (has a registration, appears in a connection) gets a Participant
 * row; an account with both signals (e.g. the live demo account) gets BOTH.
 * An account with neither signal defaults to Participant (the lower-
 * privilege, safer default for a blank account).
 *
 * New rows get fresh auto-incremented ids, NOT the old users.id - deliberate,
 * not an oversight: the new tables' registration endpoints go live the
 * moment this deploys, before anyone has a chance to run this command, so a
 * brand-new signup in that window already claims a low auto-incremented id
 * in `organizers`/`participants`. Preserving old ids here would silently
 * collide with exactly those rows. An in-memory old-id -> new-id map is
 * built instead and used to backfill every FK by hand.
 */
class SplitUsersIntoAccounts extends Command
{
    protected $signature = 'accounts:split-users {--dry-run}';

    protected $description = 'One-time cutover: split the users table into separate organizers/participants tables';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if (! Schema::hasTable('users')) {
            $this->error('No `users` table found - either this has already run, or the database is in an unexpected state.');

            return self::FAILURE;
        }

        $users = DB::table('users')->get();
        if ($users->isEmpty()) {
            $this->info('No users to migrate.');

            return self::SUCCESS;
        }

        $organizerIds = $this->organizerSignalUserIds();
        $participantIds = $this->participantSignalUserIds();

        $bothCount = $organizerIds->intersect($participantIds)->count();
        $organizerOnlyCount = $organizerIds->diff($participantIds)->count();
        $participantOnlyCount = $participantIds->diff($organizerIds)->count();
        $neitherCount = $users->pluck('id')->diff($organizerIds)->diff($participantIds)->count();

        $this->info("Total users: {$users->count()}");
        $this->info("  Organizer only: {$organizerOnlyCount}");
        $this->info("  Participant only: {$participantOnlyCount}");
        $this->info("  Both (gets two rows, one per table): {$bothCount}");
        $this->info("  Neither signal (defaults to Participant): {$neitherCount}");

        if ($dryRun) {
            $this->info('Dry run - nothing written. Re-run without --dry-run to actually migrate.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Proceed with the real migration? This renames `users` to `legacy_users_backup` when done.', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($users, $organizerIds, $participantIds) {
            $organizerIdMap = $this->createOrganizers($users, $organizerIds);
            $participantIdMap = $this->createParticipants($users, $participantIds, $organizerIds);
            $this->backfillForeignKeys($organizerIdMap, $participantIdMap);
            $this->addDiscussionAuthorCheckConstraints();
            $this->purgeLegacyTokens();

            Schema::rename('users', 'legacy_users_backup');
        });

        $this->info('Done. `users` renamed to `legacy_users_backup` (kept as a rollback source, not actively used going forward).');

        return self::SUCCESS;
    }

    private function organizerSignalUserIds()
    {
        return DB::table('events')->whereNotNull('user_id')->pluck('user_id')
            ->merge(DB::table('organization_members')->pluck('user_id'))
            ->merge(DB::table('organization_invites')->pluck('invited_by'))
            ->merge(DB::table('users')->where('role', 'admin')->pluck('id'))
            ->unique()
            ->values();
    }

    private function participantSignalUserIds()
    {
        return DB::table('registrations')->whereNotNull('user_id')->pluck('user_id')
            ->unique()
            ->values();
    }

    /** @return array<int, int> old users.id => new organizers.id */
    private function createOrganizers($users, $organizerIds): array
    {
        $map = [];

        foreach ($users->whereIn('id', $organizerIds->all()) as $u) {
            $map[$u->id] = DB::table('organizers')->insertGetId([
                'name' => $u->name,
                'email' => $u->email,
                'password' => $u->password,
                'remember_token' => $u->remember_token,
                'institution' => $u->institution,
                'avatar' => $u->avatar,
                'role' => $u->role,
                // Grandfathered straight to 'approved' - every one of these
                // accounts already existed (and, if it's an organizer at
                // all, was already acting as one) before admin approval was
                // a thing.
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => null,
                'email_verified_at' => $u->email_verified_at,
                'created_at' => $u->created_at,
                'updated_at' => $u->updated_at,
            ]);
        }

        $this->info('Created '.count($map).' organizer(s).');

        return $map;
    }

    /** @return array<int, int> old users.id => new participants.id */
    private function createParticipants($users, $participantIds, $organizerIds): array
    {
        // Neither-signal accounts default here too (see class docblock).
        $ids = $participantIds->merge($users->pluck('id')->diff($organizerIds)->diff($participantIds))->unique();
        $map = [];

        foreach ($users->whereIn('id', $ids->all()) as $u) {
            $map[$u->id] = DB::table('participants')->insertGetId([
                'name' => $u->name,
                'email' => $u->email,
                'password' => $u->password,
                'remember_token' => $u->remember_token,
                'institution' => $u->institution,
                'avatar' => $u->avatar,
                'email_verified_at' => $u->email_verified_at,
                'created_at' => $u->created_at,
                'updated_at' => $u->updated_at,
            ]);
        }

        $this->info('Created '.count($map).' participant(s).');

        return $map;
    }

    /**
     * No shortcut blanket UPDATEs here - ids aren't preserved (see class
     * docblock), so every FK has to be remapped row by row through the
     * old-id -> new-id maps built above.
     */
    private function backfillForeignKeys(array $organizerIdMap, array $participantIdMap): void
    {
        foreach (DB::table('events')->whereNotNull('user_id')->get(['id', 'user_id']) as $event) {
            if (isset($organizerIdMap[$event->user_id])) {
                DB::table('events')->where('id', $event->id)->update(['organizer_id' => $organizerIdMap[$event->user_id]]);
            }
        }

        foreach (DB::table('registrations')->whereNotNull('user_id')->get(['id', 'user_id']) as $registration) {
            if (isset($participantIdMap[$registration->user_id])) {
                DB::table('registrations')->where('id', $registration->id)->update(['participant_id' => $participantIdMap[$registration->user_id]]);
            }
        }

        foreach (DB::table('organization_members')->get(['id', 'user_id']) as $member) {
            if (isset($organizerIdMap[$member->user_id])) {
                DB::table('organization_members')->where('id', $member->id)->update(['organizer_id' => $organizerIdMap[$member->user_id]]);
            }
        }

        foreach (DB::table('organization_invites')->whereNotNull('invited_by')->get(['id', 'invited_by']) as $invite) {
            if (isset($organizerIdMap[$invite->invited_by])) {
                DB::table('organization_invites')->where('id', $invite->id)->update(['invited_by' => $organizerIdMap[$invite->invited_by]]);
            }
        }
        // Only safe to add now that every existing row's invited_by holds a
        // real organizers.id - see the schema migration for why this
        // couldn't be added any earlier. SQLite (the test suite's driver)
        // doesn't support ALTER TABLE ... ADD CONSTRAINT at all - this only
        // runs against the real Postgres production database, which is the
        // only place this constraint is meaningful anyway.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE organization_invites ADD CONSTRAINT organization_invites_invited_by_foreign FOREIGN KEY (invited_by) REFERENCES organizers(id) ON DELETE CASCADE');
        }

        // Discussion authorship: whichever new-account type this user_id
        // became depends on whether they posted as a member of *this
        // specific* organization (organizer) or as a registrant
        // (participant) - mirrors DiscussionController::authorizeAccess's
        // own "member OR registrant" check exactly, so the backfill matches
        // how each row was actually allowed to be created in the first place.
        foreach (DB::table('discussion_threads')->get(['id', 'user_id', 'organization_id']) as $thread) {
            $wasMember = DB::table('organization_members')
                ->where('organization_id', $thread->organization_id)
                ->where('user_id', $thread->user_id)
                ->exists();
            if ($wasMember && isset($organizerIdMap[$thread->user_id])) {
                DB::table('discussion_threads')->where('id', $thread->id)->update(['organizer_user_id' => $organizerIdMap[$thread->user_id]]);
            } elseif (isset($participantIdMap[$thread->user_id])) {
                DB::table('discussion_threads')->where('id', $thread->id)->update(['participant_user_id' => $participantIdMap[$thread->user_id]]);
            }
        }

        foreach (DB::table('discussion_replies')->get(['id', 'user_id', 'thread_id']) as $reply) {
            $organizationId = DB::table('discussion_threads')->where('id', $reply->thread_id)->value('organization_id');
            $wasMember = DB::table('organization_members')
                ->where('organization_id', $organizationId)
                ->where('user_id', $reply->user_id)
                ->exists();
            if ($wasMember && isset($organizerIdMap[$reply->user_id])) {
                DB::table('discussion_replies')->where('id', $reply->id)->update(['organizer_user_id' => $organizerIdMap[$reply->user_id]]);
            } elseif (isset($participantIdMap[$reply->user_id])) {
                DB::table('discussion_replies')->where('id', $reply->id)->update(['participant_user_id' => $participantIdMap[$reply->user_id]]);
            }
        }

        $this->info('Backfilled organizer_id/participant_id/invited_by/dual-author columns.');
    }

    /**
     * Only added now, after every existing row has exactly one of the two
     * columns set - adding this any earlier would reject the migration
     * outright (Postgres validates CHECK constraints against existing rows).
     * Postgres-only for the same reason as the invited_by constraint above -
     * SQLite can't ADD CONSTRAINT after the table already exists.
     */
    private function addDiscussionAuthorCheckConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE discussion_threads ADD CONSTRAINT discussion_threads_exactly_one_author
            CHECK ((organizer_user_id IS NOT NULL)::int + (participant_user_id IS NOT NULL)::int = 1)
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE discussion_replies ADD CONSTRAINT discussion_replies_exactly_one_author
            CHECK ((organizer_user_id IS NOT NULL)::int + (participant_user_id IS NOT NULL)::int = 1)
        SQL);

        $this->info('Added discussion author CHECK constraints.');
    }

    /**
     * Old Sanctum tokens for the User model are meaningless once nothing
     * issues or checks them anymore - not deleting these wouldn't break
     * anything (User::class-typed tokens just wouldn't resolve to a route
     * anyone can reach), but leaving them around only invites confusion.
     */
    private function purgeLegacyTokens(): void
    {
        $deleted = DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\User')->delete();
        $this->info("Purged {$deleted} legacy token(s).");
    }
}

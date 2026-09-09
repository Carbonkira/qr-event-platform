<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New, nullable, additive FK columns alongside the existing `user_id`
 * columns they're replacing - safe to run without touching a single
 * existing row. See accounts:split-users for the actual data backfill
 * (which also renames `users` to `legacy_users_backup`, repoints the
 * organization_invites/connections FKs that don't need a new column because
 * their existing column can only ever mean one account type, and adds the
 * discussion tables' "exactly one author" CHECK constraint once every row
 * has one set). The old `user_id` columns are deliberately left in place,
 * unused, as a rollback source - a later cleanup migration drops them once
 * this has been stable in production for a while.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('organizer_id')->nullable()->after('user_id')->constrained('organizers')->nullOnDelete();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('participant_id')->nullable()->after('user_id')->constrained('participants')->nullOnDelete();
        });

        Schema::table('organization_members', function (Blueprint $table) {
            $table->foreignId('organizer_id')->nullable()->after('user_id')->constrained('organizers')->nullOnDelete();
            // Was NOT NULL (unlike events.user_id/registrations.user_id,
            // which were already nullable) - new rows only ever populate
            // organizer_id going forward, so the old column has to actually
            // accept null now instead of just sitting unused.
            $table->foreignId('user_id')->nullable()->change();
        });

        // Two nullable author columns rather than a polymorphic morphTo -
        // there are only ever exactly two possible author kinds, and every
        // other relation in this codebase already uses plain typed FKs, not
        // morphs. The CHECK constraint enforcing exactly one is set gets
        // added by accounts:split-users once every existing row has one.
        Schema::table('discussion_threads', function (Blueprint $table) {
            $table->foreignId('organizer_user_id')->nullable()->after('user_id')->constrained('organizers')->nullOnDelete();
            $table->foreignId('participant_user_id')->nullable()->after('organizer_user_id')->constrained('participants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('discussion_replies', function (Blueprint $table) {
            $table->foreignId('organizer_user_id')->nullable()->after('user_id')->constrained('organizers')->nullOnDelete();
            $table->foreignId('participant_user_id')->nullable()->after('organizer_user_id')->constrained('participants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->change();
        });

        // invited_by keeps its existing column name - only ever meant one
        // account type, so no new column was needed - but the code writes
        // Organizer ids into it starting the moment this deploys, while
        // existing rows still hold old users.id values until
        // accounts:split-users remaps them. Its FK constraint has to come
        // off *now* (not repointed yet - that still-mixed state would
        // satisfy neither `users` nor `organizers`) and only goes back on,
        // pointed at organizers, once the backfill has remapped every
        // existing row to match.
        Schema::table('organization_invites', function (Blueprint $table) {
            $table->foreignId('invited_by')->nullable()->change();
            $table->dropForeign(['invited_by']);
        });
    }

    public function down(): void
    {
        Schema::table('organization_invites', function (Blueprint $table) {
            $table->foreign('invited_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('discussion_replies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('participant_user_id');
            $table->dropConstrainedForeignId('organizer_user_id');
        });

        Schema::table('discussion_threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('participant_user_id');
            $table->dropConstrainedForeignId('organizer_user_id');
        });

        Schema::table('organization_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_id');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('participant_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_id');
        });
    }
};

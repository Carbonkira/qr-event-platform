<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unlike MySQL, Postgres does not automatically index a column just
 * because it carries a foreign key constraint - every WHERE/JOIN on any
 * of these columns (which is most queries in this app: events by
 * organization, registrations by event/user, connections by either
 * party, ...) has been doing a full sequential scan. Cheap and safe to
 * add now, before the tables get big enough for that to actually hurt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('organization_id');
            $table->index(['status', 'is_private']); // the public listing's exact WHERE clause
            $table->index('date');
        });

        Schema::table('event_tasks', function (Blueprint $table) {
            $table->index('event_id');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->index('event_id');
            $table->index('user_id');
        });

        Schema::table('feedback', function (Blueprint $table) {
            $table->index('event_id');
            $table->index('registration_id');
        });

        Schema::table('organization_members', function (Blueprint $table) {
            // (organization_id, user_id) is already a composite unique index,
            // but that only serves lookups starting with organization_id -
            // $user->organizations() filters by user_id alone and needs its own.
            $table->index('user_id');
        });

        Schema::table('organization_invites', function (Blueprint $table) {
            $table->index('organization_id');
        });

        Schema::table('discussion_threads', function (Blueprint $table) {
            $table->index('organization_id');
            $table->index('user_id');
        });

        Schema::table('discussion_replies', function (Blueprint $table) {
            $table->index('thread_id');
            $table->index('user_id');
        });

        Schema::table('connections', function (Blueprint $table) {
            // Same reasoning as organization_members - (requester_id,
            // recipient_id) is a composite unique, but scopeInvolving()
            // queries each column independently via OR.
            $table->index('requester_id');
            $table->index('recipient_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['status', 'is_private']);
            $table->dropIndex(['date']);
        });

        Schema::table('event_tasks', function (Blueprint $table) {
            $table->dropIndex(['event_id']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['event_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('feedback', function (Blueprint $table) {
            $table->dropIndex(['event_id']);
            $table->dropIndex(['registration_id']);
        });

        Schema::table('organization_members', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('organization_invites', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
        });

        Schema::table('discussion_threads', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('discussion_replies', function (Blueprint $table) {
            $table->dropIndex(['thread_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('connections', function (Blueprint $table) {
            $table->dropIndex(['requester_id']);
            $table->dropIndex(['recipient_id']);
        });
    }
};

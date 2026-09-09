<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Certificate of Attendance feature is being removed entirely - the
 * system never actually generated or printed a certificate file, this was
 * only ever a boolean flag ("requires_certificate" on the event, hand-set by
 * the organizer at creation, and "needs_certificate" on each registration,
 * self-selected by the participant). Adviser review flagged this as
 * confusing (the system implies it does something it doesn't), so the
 * simplest fix is to drop the flag rather than keep disclaiming it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('requires_certificate');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('needs_certificate');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('requires_certificate')->default(false);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->boolean('needs_certificate')->default(false);
        });
    }
};

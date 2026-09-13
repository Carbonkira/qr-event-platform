<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverts the per-event payment_accounts column added a few days ago
 * (2026_09_11_123638) - the actual payment now happens entirely outside
 * the system (organizers advertise where to pay through their own
 * channels), so there's nothing structured left to store here. See the
 * registrations-table migration alongside this one for the participant
 * side of the same change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('payment_accounts');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('payment_accounts')->nullable()->after('price');
        });
    }
};

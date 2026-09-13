<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the pointer into the now-gone events.payment_accounts array and
 * replaces it with what the participant actually provides now: a mode
 * they pick (still useful to categorize/report on) plus free text for
 * exactly where they sent it - copy-pasted from whatever the organizer
 * posted outside the system, not matched against anything registered
 * here. receipt_number is the organizer's side of this: an OR/receipt
 * number they assign when verifying a payment (see verifyPayment()),
 * separate from payment_ref, which is the participant's own reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('payment_account_id');
            $table->string('payment_mode')->nullable()->after('payment_ref');
            $table->string('payment_destination')->nullable()->after('payment_mode');
            $table->string('receipt_number')->nullable()->after('payment_note');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'payment_destination', 'receipt_number']);
            $table->string('payment_account_id')->nullable()->after('payment_ref');
        });
    }
};

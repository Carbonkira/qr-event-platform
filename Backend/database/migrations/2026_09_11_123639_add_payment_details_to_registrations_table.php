<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The proof-of-payment details a participant submits alongside the
 * existing payment_ref/payment_screenshot: which of the event's registered
 * payment_accounts they paid into (a plain string, not a real FK - it
 * matches one entry's client-generated `id` inside that JSON array, same
 * non-relational reference custom_data's field ids already use), how much,
 * and when. Shown back to them (and the organizer) once payment is
 * verified - see PaymentVerifiedMail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('payment_account_id')->nullable()->after('payment_ref');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_account_id');
            $table->date('payment_date')->nullable()->after('payment_amount');
            $table->text('payment_note')->nullable()->after('payment_date');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['payment_account_id', 'payment_amount', 'payment_date', 'payment_note']);
        });
    }
};

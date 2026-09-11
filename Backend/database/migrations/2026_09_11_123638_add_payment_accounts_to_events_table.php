<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same shape as custom_fields/feedback_questions (Event::$casts) - a plain
 * JSON array of {id, mode, bankName, accountName, accountNumber}, not a
 * related table. An organizer can list several (a bank account, a GCash,
 * a Maya...) for one paid event; the participant picks one when submitting
 * proof of payment (see registrations.payment_account_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('payment_accounts')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('payment_accounts');
        });
    }
};

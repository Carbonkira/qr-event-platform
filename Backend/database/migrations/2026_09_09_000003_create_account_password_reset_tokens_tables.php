<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One per account type, same shape as Laravel's stock password_reset_tokens
 * (email-keyed, not a foreign key) - see config/auth.php's 'organizers'/
 * 'participants' password brokers. A single shared table would be ambiguous
 * once the same email can belong to both an organizer and a participant
 * account (e.g. the demo account, post-split) - a reset link has to be
 * unambiguous about which account it's for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('participant_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_password_reset_tokens');
        Schema::dropIfExists('organizer_password_reset_tokens');
    }
};

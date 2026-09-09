<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adviser-requested: genuinely separate participant and organizer accounts,
 * not one shared `users` table with a role flag - true separate tables and
 * auth, confirmed with the user over the cheaper "hide organizer UI"
 * alternative. Purely additive - the old `users` table is untouched here;
 * see accounts:split-users for the actual data migration and cutover
 * (renames `users` to `legacy_users_backup` once every row has been copied
 * into whichever of these two tables it belongs in, possibly both).
 *
 * Both tables carry email_verified_at (see Organizer/Participant models,
 * each implements MustVerifyEmail independently) and their own password
 * reset token table (see the next migration) - email is no longer globally
 * unique now that it's scoped per account type, so the two account types
 * can't share Laravel's stock password_reset_tokens table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->string('institution')->nullable();
            $table->string('avatar')->nullable();
            // Same two fields/meaning as the old users.role and the Phase 4
            // approval columns (see EnsureOrganizerApproved) - carried over
            // as-is onto the account type they actually describe.
            $table->string('role')->default('organizer'); // organizer|admin
            $table->string('approval_status')->default('pending'); // pending|approved|rejected
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('organizers')->nullOnDelete();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->string('institution')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
        Schema::dropIfExists('organizers');
    }
};

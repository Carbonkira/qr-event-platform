<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which organization a new organizer picked from the signup
 * dropdown, before the admin has approved anything. Deliberately not an
 * actual organization_members row yet - that's only created once the admin
 * approves the account (see OrganizerApprovalController::approve()),
 * same "admin has to actually look at this first" gate every other
 * organizer capability already goes through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->foreignId('requested_organization_id')->nullable()->after('institution')
                ->constrained('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_organization_id');
        });
    }
};

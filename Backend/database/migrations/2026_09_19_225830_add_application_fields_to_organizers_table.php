<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the admin needs in front of them to actually vet an organizer
 * application: a way to reach the applicant, and - if their organization
 * isn't one the admin has already created - the name and address of the one
 * they're asking for (address is for verification/security, never shown
 * publicly). All nullable: existing accounts predate these, and an
 * applicant who picked an existing organization (requested_organization_id)
 * has nothing to put in the requested_organization_* pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->string('contact_number', 30)->nullable()->after('institution');
            $table->string('requested_organization_name')->nullable()->after('requested_organization_id');
            $table->string('requested_organization_address')->nullable()->after('requested_organization_name');
        });
    }

    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->dropColumn(['contact_number', 'requested_organization_name', 'requested_organization_address']);
        });
    }
};

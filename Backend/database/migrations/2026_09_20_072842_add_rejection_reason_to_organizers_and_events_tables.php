<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why the admin turned an application down, in their own words - optional,
 * set from the reject dialog on the Approvals page and included in the
 * applicant's rejection email. Nullable: most rejections won't have one, and
 * everything decided before this existed doesn't.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('approved_by');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};

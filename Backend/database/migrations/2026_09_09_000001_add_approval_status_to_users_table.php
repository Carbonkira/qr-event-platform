<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adviser-requested: a prospective organizer meets with the system admin in
 * person before their account can do anything organizer-side (create/manage
 * events, orgs, etc.) - the admin then approves or rejects them here,
 * modeled on the existing event approve/reject pattern. Every account that
 * already exists is grandfathered straight to 'approved' in this same
 * migration so nobody already using the system (including the live demo
 * account and its real organizations) is locked out retroactively - only
 * accounts created after this ships start at the column's 'pending' default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('approval_status')->default('pending'); // pending|approved|rejected
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('users')->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'approved_at']);
        });
    }
};

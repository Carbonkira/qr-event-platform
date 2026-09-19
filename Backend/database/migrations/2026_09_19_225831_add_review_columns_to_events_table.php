<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A record of who decided on an event and when, so the Approvals page can
 * show a history of accepted/rejected events over time instead of just the
 * current queue. `status` alone can't do this - it moves on (an approved
 * event later becomes completed or cancelled), so the decision itself is
 * stored separately. Events decided before this existed are backfilled from
 * their current status, with updated_at as the best available decision time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('review_decision', 20)->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('review_decision');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('organizers')->nullOnDelete();
        });

        DB::table('events')->whereIn('status', ['approved', 'completed'])->update([
            'review_decision' => 'approved',
            'reviewed_at' => DB::raw('updated_at'),
        ]);
        DB::table('events')->where('status', 'rejected')->update([
            'review_decision' => 'rejected',
            'reviewed_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_decision', 'reviewed_at']);
        });
    }
};

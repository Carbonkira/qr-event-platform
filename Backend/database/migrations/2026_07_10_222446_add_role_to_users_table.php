<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 'organizer' can do everything except approve/reject events (see
        // EventController::authorizeAdmin) - closes the self-approval gap
        // where any account could approve its own or anyone else's event.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('organizer')->after('institution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

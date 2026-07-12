<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Moving off the single-row (id=1) model - see organization_members below,
// which is what actually determines who belongs to which organization now.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('logo')->nullable()->after('privacy_policy_url');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['slug', 'logo']);
        });
    }
};

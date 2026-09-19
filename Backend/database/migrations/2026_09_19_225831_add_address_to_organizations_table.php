<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The organization's physical address - there so the admin can verify an
 * organization is real before vouching for it. Deliberately hidden from
 * every public response (see Organization::$hidden); only an admin or the
 * organization's own members ever see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('address')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};

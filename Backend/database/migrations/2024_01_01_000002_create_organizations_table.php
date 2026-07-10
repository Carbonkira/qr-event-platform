<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Single-row table (id=1) mirroring the mock's global org profile - see
// plan §1 and §7 ("No multi-tenant orgs").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('organized_by')->nullable();
            $table->string('email')->nullable();
            $table->string('industry')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('facebook')->nullable();
            $table->string('website')->nullable();
            $table->string('twitter')->nullable();
            $table->string('privacy_policy_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

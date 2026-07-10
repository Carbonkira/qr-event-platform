<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // Nullable so a draft (status=draft) can be saved before every
            // field is filled in - see EventController::validated($draft).
            $table->string('type')->nullable();
            $table->boolean('is_private')->default(false);
            $table->string('slug')->unique();
            $table->string('private_link')->nullable();
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->string('location')->nullable();
            // Google Maps coordinates (plan §3) - nullable until a location
            // is picked on the map (or if the frontend falls back to plain
            // text inputs with no API key configured).
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->date('date')->nullable();
            // Stored as "HH:MM" strings, matching the mock (App.jsx uses
            // plain "08:00"/"17:00" strings, not full timestamps).
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->string('organized_by')->nullable();
            $table->string('industry')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            // Kept as a validated string rather than a native Postgres enum
            // column, so allowed values can change later without a DB-level
            // enum migration; EventController validates the allowed set.
            // draft: not yet submitted for approval, only visible to its organizer.
            $table->string('status')->default('pending'); // draft|pending|approved|rejected|completed
            $table->boolean('feedback_enabled')->default(true);
            $table->string('image')->nullable();
            $table->boolean('requires_certificate')->default(false);
            $table->string('pricing')->default('free'); // free|paid|walk-in
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('allow_walk_ins')->default(true);
            $table->json('socials')->nullable();
            $table->string('privacy_policy_url')->nullable();
            $table->json('custom_fields')->nullable();
            // Organizer-configurable feedback form. Null means "use the
            // built-in 5 rating questions" (see Event::defaultFeedbackQuestions()).
            $table->json('feedback_questions')->nullable();
            $table->json('tags')->nullable();
            // AI feedback summary cache (plan §4).
            $table->text('ai_summary')->nullable();
            $table->timestamp('ai_summary_generated_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

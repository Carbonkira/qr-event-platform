<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            // 1-5 star ratings per question. tinyInteger maps to smallint on
            // Postgres (no native 1-byte int type there).
            $table->tinyInteger('q1');
            $table->tinyInteger('q2');
            $table->tinyInteger('q3');
            $table->tinyInteger('q4');
            $table->tinyInteger('q5');
            $table->text('comment')->nullable();
            // Answers to the event's organizer-defined extra feedback
            // questions (event.feedback_questions), keyed by question id.
            $table->json('custom_answers')->nullable();
            $table->boolean('is_highlighted')->default(false);
            $table->string('badge')->nullable(); // "⭐ Top Reviewer" | "🏆 Super Reviewer" | null
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};

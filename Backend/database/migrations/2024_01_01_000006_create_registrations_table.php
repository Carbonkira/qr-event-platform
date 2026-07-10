<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // The account that registered. Null only for organizer-facilitated
            // walk-ins, which don't require an account (see RegistrationController).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            // Answers to the event's custom_fields, keyed by field id.
            $table->json('custom_data')->nullable();
            $table->string('qr_code')->unique(); // "QR-E{eventNum}-{P|WI}{seq}"
            $table->boolean('attended')->default(false);
            $table->timestamp('check_in_time')->nullable();
            $table->boolean('feedback_submitted')->default(false);
            $table->boolean('is_walk_in')->default(false);
            $table->boolean('needs_certificate')->default(false);
            // True once the event's capacity was already full at signup time.
            // See RegistrationController::createRegistration() / promote().
            $table->boolean('waitlisted')->default(false);
            $table->string('payment_status')->nullable(); // pending|verified|rejected
            $table->string('payment_ref')->nullable();
            $table->string('payment_screenshot')->nullable(); // storage path, disk "public"
            // Set once a reminder email has gone out, so the reminder command
            // doesn't re-send on its next run.
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};

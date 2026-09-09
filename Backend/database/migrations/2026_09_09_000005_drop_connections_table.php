<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The participant-networking "connections" feature has been removed outright. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('connections');
    }

    public function down(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('participants')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('participants')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['requester_id', 'recipient_id']);
            $table->index('requester_id');
            $table->index('recipient_id');
        });
    }
};

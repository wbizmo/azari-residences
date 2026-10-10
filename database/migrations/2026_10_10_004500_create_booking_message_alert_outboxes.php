<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_message_alert_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_message_id')->unique()->constrained('booking_messages')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->string('last_error', 200)->nullable();
            $table->timestamps();
            $table->index(['state', 'next_attempt_at']);
            $table->index(['state', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_message_alert_outboxes');
    }
};

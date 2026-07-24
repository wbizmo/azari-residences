<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'guest_first_name')) {
                $table->string('guest_first_name')->nullable()->after('guest_name');
            }
            if (! Schema::hasColumn('bookings', 'guest_last_name')) {
                $table->string('guest_last_name')->nullable()->after('guest_first_name');
            }
            if (! Schema::hasColumn('bookings', 'nationality')) {
                $table->string('nationality', 100)->nullable();
            }
            if (! Schema::hasColumn('bookings', 'address')) {
                $table->string('address')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'city')) {
                $table->string('city', 120)->nullable();
            }
            if (! Schema::hasColumn('bookings', 'country')) {
                $table->string('country', 120)->nullable();
            }
            if (! Schema::hasColumn('bookings', 'arrival_time')) {
                $table->time('arrival_time')->nullable();
            }
        });

        if (! Schema::hasTable('booking_guests')) {
            Schema::create('booking_guests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->string('type', 20)->index();
                $table->unsignedInteger('position');
                $table->string('first_name');
                $table->string('last_name');
                $table->boolean('is_lead')->default(false)->index();
                $table->timestamps();
                $table->unique(['booking_id', 'type', 'position']);
            });
        }

        if (! Schema::hasTable('guest_identity_documents')) {
            Schema::create('guest_identity_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('booking_guest_id')->constrained()->cascadeOnDelete();
                $table->string('document_type', 40);
                $table->string('disk', 40)->default('local');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('size_bytes');
                $table->string('sha256', 64)->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_identity_documents');
        Schema::dropIfExists('booking_guests');
    }
};

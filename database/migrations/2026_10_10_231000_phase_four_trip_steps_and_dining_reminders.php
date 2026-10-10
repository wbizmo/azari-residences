<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dining_partners', function (Blueprint $t) {
            $t->decimal('latitude',10,7)->nullable();
            $t->decimal('longitude',10,7)->nullable();
            $t->index(['status','latitude','longitude'], 'dining_geolocation_idx');
        });
        Schema::table('dining_requests', function (Blueprint $t) {
            $t->timestamp('arrival_reminder_sent_at')->nullable()->index();
        });
        Schema::create('trip_assembly_steps', function (Blueprint $t) {
            $t->id();
            $t->uuid('trip_assembly_id');
            $t->foreign('trip_assembly_id')->references('id')->on('trip_assemblies')->restrictOnDelete();
            $t->string('item_type',30);
            $t->string('item_id',100);
            $t->string('status',32)->default('awaiting_partner');
            $t->string('provider_reference',160)->nullable();
            $t->string('operation_key',128)->unique();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamps();
            $t->unique(['trip_assembly_id','item_type','item_id'], 'trip_step_component_unique');
            $t->index(['status','updated_at'], 'trip_step_recovery_index');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('trip_assembly_steps');
        Schema::table('dining_requests', function (Blueprint $t) {
            $t->dropColumn('arrival_reminder_sent_at');
        });
        Schema::table('dining_partners', function (Blueprint $t) {
            $t->dropIndex('dining_geolocation_idx');
            $t->dropColumn(['latitude','longitude']);
        });
    }
};

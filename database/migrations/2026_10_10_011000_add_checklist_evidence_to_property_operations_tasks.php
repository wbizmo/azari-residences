<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_operations_tasks', function (Blueprint $table): void {
            $table->json('checklist')->nullable()->after('notes');
            $table->string('evidence_path')->nullable()->after('checklist');
            $table->string('evidence_name')->nullable()->after('evidence_path');
            $table->string('evidence_mime', 100)->nullable()->after('evidence_name');
        });
    }

    public function down(): void
    {
        Schema::table('property_operations_tasks', function (Blueprint $table): void {
            $table->dropColumn(['checklist', 'evidence_path', 'evidence_name', 'evidence_mime']);
        });
    }
};

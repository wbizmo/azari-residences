<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->timestamp('sla_alerted_at')->nullable();
            $table->index(['status', 'severity', 'sla_due_at', 'sla_alerted_at'], 'support_sla_escalation_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('support_sla_escalation_lookup');
            $table->dropColumn('sla_alerted_at');
        });
    }
};

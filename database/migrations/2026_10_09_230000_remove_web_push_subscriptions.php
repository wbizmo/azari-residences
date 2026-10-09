<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove unused browser endpoint tokens from pre-existing installations.
        Schema::dropIfExists('web_push_subscriptions');
    }

    public function down(): void
    {
        // Deliberately do not restore device secrets or obsolete subscriptions.
    }
};

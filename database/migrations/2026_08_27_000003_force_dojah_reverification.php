<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('identity_verifications')) {
            return;
        }

        DB::table('identity_verifications')
            ->where('provider', 'dojah')
            ->where('status', 'verified')
            ->update([
                'status' => 'expired',
                'verified_at' => null,
                'failed_at' => null,
                'failure_reason' => 'Reverification required after Azari moved to the Dojah-only identity flow.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Verification state is security-sensitive and must never be restored
        // automatically by rolling a migration back.
    }
};

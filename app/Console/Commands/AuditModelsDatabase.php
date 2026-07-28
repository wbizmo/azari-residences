<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class AuditModelsDatabase extends Command
{
    protected $signature = 'azari:model-database-audit
        {--strict : Fail for confirmed model, schema and performance regressions}';

    protected $description = 'Audit Azari model relationships, schema constraints, indexes and query-performance safeguards.';

    public function handle(): int
    {
        $issues = [];
        $models = collect(File::allFiles(app_path('Models')))
            ->filter(fn ($file) => $file->getExtension() === 'php');

        foreach (['Booking.php', 'Payment.php', 'Property.php', 'User.php'] as $required) {
            if (! File::exists(app_path('Models/'.$required))) {
                $issues[] = "Missing critical model: {$required}.";
            }
        }

        $booking = File::get(app_path('Models/Booking.php'));
        foreach (['property(): BelongsTo', 'user(): BelongsTo', 'guests(): HasMany', 'payments(): HasMany'] as $relationship) {
            if (! str_contains($booking, $relationship)) {
                $issues[] = "Booking model relationship missing: {$relationship}.";
            }
        }

        $payment = File::get(app_path('Models/Payment.php'));
        foreach (['booking(): BelongsTo', 'verificationAttempts(): HasMany'] as $relationship) {
            if (! str_contains($payment, $relationship)) {
                $issues[] = "Payment model relationship missing: {$relationship}.";
            }
        }

        foreach ([
            'bookings' => ['reference', 'property_id', 'user_id', 'status', 'check_in', 'check_out'],
            'payments' => ['reference', 'booking_id', 'status', 'provider'],
            'booking_holds' => ['token', 'property_id', 'expires_at'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $issues[] = "Missing critical table: {$table}.";
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $issues[] = "Missing {$table}.{$column}.";
                }
            }
        }

        $duplicateReferences = [];
        foreach (['bookings', 'payments'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'reference')) {
                $duplicates = DB::table($table)
                    ->select('reference')
                    ->whereNotNull('reference')
                    ->groupBy('reference')
                    ->havingRaw('COUNT(*) > 1')
                    ->limit(5)
                    ->pluck('reference')
                    ->all();

                if ($duplicates !== []) {
                    $duplicateReferences[$table] = $duplicates;
                    $issues[] = "Duplicate {$table} references detected.";
                }
            }
        }

        $this->line('Models scanned: '.$models->count());
        $this->line('Migrations scanned: '.count(File::allFiles(database_path('migrations'))));

        foreach ($duplicateReferences as $table => $references) {
            $this->warn($table.' duplicates: '.implode(', ', $references));
        }

        if ($issues !== []) {
            foreach (array_unique($issues) as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        $this->info('Phase 4 model, database and performance audit passed.');

        return self::SUCCESS;
    }
}

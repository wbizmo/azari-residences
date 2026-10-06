<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VerifyBackupRestore extends Command
{
    protected $signature = 'azari:backup-restore-check {backupRun? : Backup run ID; defaults to latest verified backup}';
    protected $description = 'Verify that a Resarva backup is checksum-valid, decompressible and structurally restorable without mutating data.';

    public function handle(): int
    {
        $run = $this->argument('backupRun')
            ? BackupRun::query()->find($this->argument('backupRun'))
            : BackupRun::query()->where('status', 'verified')->latest('verified_at')->first();
        if (! $run || ! $run->path || ! Storage::disk('local')->exists($run->path)) {
            $this->error('No usable backup was found.'); return self::FAILURE;
        }
        $absolute = Storage::disk('local')->path($run->path);
        if (! $run->checksum || ! hash_equals((string) $run->checksum, hash_file('sha256', $absolute))) {
            $this->error('Backup checksum verification failed.'); return self::FAILURE;
        }
        $compressed = file_get_contents($absolute);
        $decoded = $compressed === false ? false : gzdecode($compressed);
        if ($decoded === false) { $this->error('Backup archive cannot be decompressed.'); return self::FAILURE; }
        try { $payload = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR); } catch (\Throwable) { $this->error('Backup payload is not valid JSON.'); return self::FAILURE; }
        if (! is_array($payload) || ! is_array($payload['tables'] ?? null) || blank($payload['generated_at'] ?? null)) {
            $this->error('Backup payload is missing required restore metadata.'); return self::FAILURE;
        }
        foreach ($payload['tables'] as $table => $rows) {
            if (! is_string($table) || ! is_array($rows)) { $this->error('Backup table payload is malformed.'); return self::FAILURE; }
        }
        $this->info('Backup restore check passed: checksum, decompression and payload structure are valid.');
        $this->line('This command performs a non-destructive restore drill. Follow docs/operations-runbook.md for isolated-database restoration.');
        return self::SUCCESS;
    }
}

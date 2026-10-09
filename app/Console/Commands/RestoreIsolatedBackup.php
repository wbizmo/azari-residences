<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * An opt-in, fail-closed restore rehearsal into a separately configured,
 * disposable, already-migrated database. NEVER writes to the source database.
 */
class RestoreIsolatedBackup extends Command
{
    protected $signature = 'resavar:restore-drill
        {backupRun : ID of a verified encrypted database archive}
        {--target=resavar_restore : Explicit separate restore DB connection}
        {--confirm-isolated= : Must equal RESTORE_TO_ISOLATED_DATABASE}';

    protected $description = 'Restore a verified archive into an EMPTY isolated database and compare all table row counts.';

    public function handle(): int
    {
        $targetName = (string) $this->option('target');
        if ($this->option('confirm-isolated') !== 'RESTORE_TO_ISOLATED_DATABASE') {
            $this->error('Explicit isolated restore confirmation is required.');
            return self::FAILURE;
        }

        $sourceName = (string) config('database.default');
        $targetConfig = config("database.connections.{$targetName}");
        if ($targetName === $sourceName || ! is_array($targetConfig)
            || ! in_array($targetConfig['driver'] ?? null, ['mysql', 'mariadb', 'sqlite'], true)
            || filled($targetConfig['url'] ?? null)
            || blank($targetConfig['database'] ?? null)) {
            $this->error('An explicit independent, non-URL restore database is required.');
            return self::FAILURE;
        }

        // Fail closed even when the source is configured via DB_URL. Resolve
        // the actual active source database name, not an environment guess.
        $sourceDatabase = (string) DB::connection($sourceName)->getDatabaseName();
        $targetDatabase = (string) $targetConfig['database'];
        if (strcasecmp($targetDatabase, $sourceDatabase) === 0
            || ($sourceDriverName = DB::connection($sourceName)->getDriverName()) === 'sqlite'
                && realpath($targetDatabase) !== false
                && realpath($targetDatabase) === realpath($sourceDatabase)
            || ! preg_match('/(?:restore|drill|isolat)/i', $targetDatabase)) {
            $this->error('Restore database name must be distinct and visibly isolated.');
            return self::FAILURE;
        }

        $sourceDriver = DB::connection($sourceName)->getDriverName();
        if ($sourceDriver !== $targetConfig['driver']) {
            $this->error('Source and target must use the same database driver.');
            return self::FAILURE;
        }

        $backup = BackupRun::query()
            ->whereKey($this->argument('backupRun'))
            ->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->first();

        if (! $backup || ! $backup->path || ! Storage::disk('private')->exists($backup->path)) {
            $this->error('A previously verified private encrypted archive is required.');
            return self::FAILURE;
        }

        // Full checksum, decryption, table-row and terminal-marker validation
        // BEFORE even connecting to the target for writes.
        if ($this->call('azari:backup-restore-check', ['backupRun' => $backup->getKey()]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $path = Storage::disk('private')->path($backup->path);
        $started = microtime(true);

        try {
            $tables = $this->readTableMetadata($path);
            $target = DB::connection($targetName);
            $schema = Schema::connection($targetName);

            $targetTables = array_values(array_filter(
                $schema->getTableListing(null, false),
                fn (string $name): bool => ! str_starts_with($name, 'sqlite_')
            ));
            sort($targetTables);
            $archiveTables = array_keys($tables);
            sort($archiveTables);

            if ($targetTables !== $archiveTables) {
                throw new \RuntimeException('Target database schema/table inventory does not match the archive.');
            }

            // After migrations, only their tracking rows may exist. Never
            // overwrite user data or an existing recovery rehearsal.
            foreach ($tables as $table => $meta) {
                $columns = $schema->getColumnListing($table);
                sort($columns);
                $expected = $meta['columns'];
                sort($expected);
                if ($columns !== $expected) {
                    throw new \RuntimeException("Schema mismatch for archive table [{$table}].");
                }

                if ($table !== 'migrations' && $target->table($table)->exists()) {
                    throw new \RuntimeException('Restore target is not empty; refusing all writes.');
                }
            }

            $schema->disableForeignKeyConstraints();
            try {
                $target->transaction(function () use ($target, $path, $tables): void {
                    // Replacing the migration tracking rows is safe ONLY on
                    // the preflighted, otherwise empty target.
                    $target->table('migrations')->delete();
                    $this->restoreRows($path, $target, $tables);

                    foreach ($tables as $table => $meta) {
                        if ((int) $target->table($table)->count() !== $meta['rows']) {
                            throw new \RuntimeException("Restored row count mismatch for [{$table}].");
                        }
                    }
                }, 1);
            } finally {
                $schema->enableForeignKeyConstraints();
            }

            $duration = round(microtime(true) - $started, 2);
            AuditLog::record('backup.isolated_restore_drill', $backup, [], [
                'target_connection' => $targetName,
                'tables_restored' => count($tables),
                'duration_seconds' => $duration,
            ]);
            $this->info("Isolated database restore verified: ".count($tables)." tables; {$duration}s.");
            $this->warn('Private media/offsite recovery and operational cutover are separate drills.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            // Never put decrypted row values, SQL bindings, PII or secrets
            // from QueryException messages into application logs.
            Log::warning('Isolated recovery drill did not complete.', [
                'exception_class' => $exception::class,
                'target_connection' => $targetName,
            ]);
            $this->error('Isolated restore refused or failed. Target must be discarded and reprovisioned.');
            return self::FAILURE;
        }
    }

    /**
     * The structural verifier has checked all ciphertext/row segments first.
     * Read immutable table inventory before target mutation.
     *
     * @return array<string, array{columns: list<string>, rows:int}>
     */
    private function readTableMetadata(string $path): array
    {
        $stream = gzopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Cannot read encrypted archive.');
        }
        $tables = [];
        try {
            while (($line = gzgets($stream)) !== false) {
                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($record)) {
                    throw new \RuntimeException('Malformed archive record.');
                }
                if (($record['kind'] ?? null) === 'table') {
                    $tables[$record['name']] = ['columns' => $record['columns'], 'rows' => 0];
                } elseif (($record['kind'] ?? null) === 'table_end') {
                    $tables[$record['name']]['rows'] = (int) $record['rows'];
                }
            }
        } finally {
            gzclose($stream);
        }
        if ($tables === [] || ! isset($tables['migrations'])) {
            throw new \RuntimeException('Archive metadata is incomplete.');
        }
        return $tables;
    }

    /** @param array<string, array{columns:list<string>, rows:int}> $tables */
    private function restoreRows(string $path, \Illuminate\Database\Connection $target, array $tables): void
    {
        $stream = gzopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Cannot reopen verified archive.');
        }
        $table = null;
        $seen = [];
        try {
            while (($line = gzgets($stream)) !== false) {
                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                switch ($record['kind'] ?? null) {
                    case 'table':
                        $table = $record['name'];
                        $seen[$table] = 0;
                        break;
                    case 'encrypted_rows':
                        if (! isset($tables[$table]) || $record['table'] !== $table) {
                            throw new \RuntimeException('Unexpected archive table segment.');
                        }
                        $rows = json_decode(Crypt::decryptString($record['ciphertext']), true, flags: JSON_THROW_ON_ERROR);
                        if (! is_array($rows) || ! array_is_list($rows)) {
                            throw new \RuntimeException('Invalid archive rows.');
                        }
                        $expectedColumns = $tables[$table]['columns'];
                        sort($expectedColumns);
                        foreach ($rows as $row) {
                            if (! is_array($row) || array_is_list($row)) {
                                throw new \RuntimeException('Invalid archived row shape.');
                            }
                            $keys = array_keys($row);
                            sort($keys);
                            if ($keys !== $expectedColumns) {
                                throw new \RuntimeException('Archived row differs from target schema.');
                            }
                        }
                        foreach (array_chunk($rows, 50) as $chunk) {
                            $target->table($table)->insert($chunk);
                        }
                        $seen[$table] += count($rows);
                        break;
                    case 'table_end':
                        if ($table !== $record['name'] || $seen[$table] !== $tables[$table]['rows']) {
                            throw new \RuntimeException('Restored archive count mismatch.');
                        }
                        $table = null;
                        break;
                    case 'end':
                        if ($table !== null || count($seen) !== count($tables)) {
                            throw new \RuntimeException('Incomplete restore stream.');
                        }
                        return;
                }
            }
            throw new \RuntimeException('Restore did not reach end of archive.');
        } finally {
            gzclose($stream);
        }
    }
}

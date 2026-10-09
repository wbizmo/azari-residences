<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AzariBackup extends Command
{
    protected $signature = 'azari:backup {--verify : Verify the archive after creation}';
    protected $description = 'Create an encrypted, complete application database archive on private storage.';

    public function handle(): int
    {
        $run = BackupRun::query()->create([
            'driver' => config('database.default'), 'status' => 'started', 'started_at' => now(),
        ]);
        $directory = 'backups/'.now()->format('Y/m');
        $path = $directory.'/resavar-'.now()->format('Ymd-His').'-'.Str::random(10).'.rsvbak.gz';
        $disk = Storage::disk('private');
        $stream = null;

        try {
            if (! filled(config('app.key'))) {
                throw new \RuntimeException('Application encryption key is missing.');
            }

            $disk->makeDirectory($directory);
            $absolute = $disk->path($path);
            $stream = gzopen($absolute, 'wb6');
            if ($stream === false) {
                throw new \RuntimeException('Cannot open protected backup destination.');
            }

            $tables = array_values(array_filter(
                Schema::getTableListing(null, false),
                fn (string $name): bool => ! str_starts_with($name, 'sqlite_')
            ));
            sort($tables, SORT_STRING);
            if (! in_array('bookings', $tables, true) || ! in_array('payments', $tables, true)
                || ! in_array('users', $tables, true) || ! in_array('migrations', $tables, true)) {
                throw new \RuntimeException('Required database tables are missing; refusing incomplete backup.');
            }

            $this->write($stream, [
                'kind' => 'header', 'format' => 'resavar.encrypted.ndjson.v2',
                'generated_at' => now()->toIso8601String(),
                'database_driver' => config('database.default'),
                'table_count' => count($tables),
            ]);

            // A consistent database snapshot: every table is captured from
            // one transaction, not disjoint exports of financial/inventory data.
            DB::transaction(function () use ($tables, $stream): void {
                foreach ($tables as $table) {
                    $columns = Schema::getColumnListing($table);
                    if ($columns === []) {
                        throw new \RuntimeException('A table has no exportable columns.');
                    }
                    $this->write($stream, [
                        'kind' => 'table', 'name' => $table, 'columns' => $columns,
                    ]);
                    $count = 0;
                    DB::table($table)->orderBy(in_array('id', $columns, true) ? 'id' : $columns[0])
                        ->chunk(250, function ($rows) use ($stream, $table, &$count): void {
                            $batch = $rows->map(fn ($row) => (array) $row)->all();
                            $this->write($stream, [
                                'kind' => 'encrypted_rows', 'table' => $table,
                                'ciphertext' => Crypt::encryptString(json_encode(
                                    $batch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                                )),
                            ]);
                            $count += count($batch);
                        });
                    $this->write($stream, [
                        'kind' => 'table_end', 'name' => $table, 'rows' => $count,
                    ]);
                }
            }, 1);

            $this->write($stream, ['kind' => 'end', 'tables' => count($tables)]);
            gzclose($stream);
            $stream = null;
            $checksum = hash_file('sha256', $absolute);
            if (! $checksum) {
                throw new \RuntimeException('Backup archive checksum could not be read.');
            }

            $run->forceFill([
                'path' => $path,
                'status' => 'completed', 'size_bytes' => filesize($absolute),
                'checksum' => $checksum, 'finished_at' => now(), 'verified_at' => null,
                'metadata' => [
                    'format' => 'resavar.encrypted.ndjson.v2',
                    'encrypted' => true, 'tables' => count($tables),
                    'retention_days' => (int) env('AZARI_BACKUP_RETENTION_DAYS', 30),
                ],
            ])->save();

            if ($this->option('verify')) {
                $code = $this->call('azari:backup-restore-check', ['backupRun' => $run->getKey()]);
                if ($code !== self::SUCCESS) {
                    $run->update(['status' => 'verification_failed', 'verified_at' => null]);
                    return self::FAILURE;
                }
                $run->update(['status' => 'verified', 'verified_at' => now()]);
            }

            AuditLog::record('backup.created', $run, [], [
                'format' => 'resavar.encrypted.ndjson.v2', 'tables' => count($tables),
            ]);
            $this->info($this->option('verify')
                ? 'Encrypted backup structurally verified in private storage.'
                : 'Encrypted backup created in private storage; structural verification is still required.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            if (is_resource($stream)) {
                gzclose($stream);
            }
            $disk->delete($path);
            $run->update([
                'status' => 'failed', 'finished_at' => now(),
                'safe_error' => 'Archive creation failed: '.$exception::class,
            ]);
            report($exception);
            $this->error('Backup failed; see protected application logs.');
            return self::FAILURE;
        }
    }

    /** @param resource $stream */
    private function write($stream, array $record): void
    {
        $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
        if (gzwrite($stream, $line) !== strlen($line)) {
            throw new \RuntimeException('Backup stream write failed.');
        }
    }
}

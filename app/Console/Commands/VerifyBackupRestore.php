<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class VerifyBackupRestore extends Command
{
    protected $signature = 'azari:backup-restore-check {backupRun? : Backup ID; defaults to latest verified backup}';
    protected $description = 'Verify encrypted archive integrity and all table row counts without restoring production.';

    public function handle(): int
    {
        $run = $this->argument('backupRun')
            ? BackupRun::query()->find($this->argument('backupRun'))
            : BackupRun::query()->where('status', 'verified')->latest('verified_at')->first();

        if (! $run || ! $run->path || ! Storage::disk('private')->exists($run->path)) {
            $this->error('No private backup is available.');
            return self::FAILURE;
        }
        $file = Storage::disk('private')->path($run->path);
        if (! $run->checksum || ! hash_equals((string) $run->checksum, (string) hash_file('sha256', $file))) {
            $this->error('Archive integrity checksum failed.');
            return self::FAILURE;
        }

        $stream = gzopen($file, 'rb');
        if ($stream === false) {
            $this->error('Cannot decompress backup.');
            return self::FAILURE;
        }

        try {
            $header = $this->read($stream);
            if (($header['kind'] ?? null) !== 'header'
                || ($header['format'] ?? null) !== 'resavar.encrypted.ndjson.v2') {
                throw new \UnexpectedValueException('Legacy/unsupported or incomplete archive format.');
            }

            $counts = [];
            $table = null;
            $ended = false;
            while (! gzeof($stream)) {
                $record = $this->read($stream);
                if ($record === null) {
                    break;
                }
                switch ($record['kind'] ?? '') {
                    case 'table':
                        if ($table !== null || ! is_string($record['name'] ?? null)
                            || ! is_array($record['columns'] ?? null) || $record['columns'] === []
                            || array_key_exists($record['name'], $counts)) {
                            throw new \UnexpectedValueException('Invalid or duplicate table declaration.');
                        }
                        $table = $record['name'];
                        $counts[$table] = 0;
                        break;
                    case 'encrypted_rows':
                        if ($table === null || ($record['table'] ?? null) !== $table
                            || ! is_string($record['ciphertext'] ?? null)) {
                            throw new \UnexpectedValueException('Invalid encrypted row segment.');
                        }
                        $rows = json_decode(Crypt::decryptString($record['ciphertext']), true, flags: JSON_THROW_ON_ERROR);
                        if (! is_array($rows) || ! array_is_list($rows)) {
                            throw new \UnexpectedValueException('Malformed row segment.');
                        }
                        $counts[$table] += count($rows);
                        break;
                    case 'table_end':
                        if ($table === null || ($record['name'] ?? null) !== $table
                            || (int) ($record['rows'] ?? -1) !== $counts[$table]) {
                            throw new \UnexpectedValueException('Table row count is incomplete.');
                        }
                        $table = null;
                        break;
                    case 'end':
                        if ($table !== null || (int) ($record['tables'] ?? 0) !== count($counts)
                            || count($counts) !== (int) ($header['table_count'] ?? 0)) {
                            throw new \UnexpectedValueException('Archive table count is incomplete.');
                        }
                        $ended = true;
                        break;
                    default:
                        throw new \UnexpectedValueException('Unrecognized archive record.');
                }
                if ($ended) {
                    if (! gzeof($stream) && trim((string) gzgets($stream)) !== '') {
                        throw new \UnexpectedValueException('Unexpected trailing backup data.');
                    }
                    break;
                }
            }

            foreach (['users', 'bookings', 'payments', 'inventory_dates', 'migrations'] as $required) {
                if (! array_key_exists($required, $counts)) {
                    throw new \UnexpectedValueException('Required data missing from archive.');
                }
            }
            if (! $ended || $counts === []) {
                throw new \UnexpectedValueException('Backup ended before the final checkpoint.');
            }
            gzclose($stream);
            $this->info('Complete encrypted archive checksum, ciphertext and table row counts verified.');
            $this->line('A real restore still requires an isolated database and a supervised drill.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            gzclose($stream);
            report($exception);
            $this->error('Backup integrity or decryption check failed.');
            return self::FAILURE;
        }
    }

    /** @param resource $stream */
    private function read($stream): ?array
    {
        $line = gzgets($stream);
        if ($line === false) {
            return null;
        }
        $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($record)) {
            throw new \UnexpectedValueException('Malformed archive line.');
        }
        return $record;
    }
}

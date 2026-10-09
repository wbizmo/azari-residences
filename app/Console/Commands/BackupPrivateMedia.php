<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\Operations\PrivateMediaRecoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupPrivateMedia extends Command
{
    protected $signature = 'resavar:backup-private-media
        {--manifest= : Existing offsite manifest to verify}
        {--restore-drill : Restore an existing verified archive into isolated media root}
        {--confirm-isolated= : Must equal RESTORE_PRIVATE_MEDIA_TO_ISOLATED_ROOT}';

    protected $description = 'Encrypted offsite private media archive, verification, and isolated restore rehearsal.';

    public function handle(PrivateMediaRecoveryService $service): int
    {
        $disk = (string) config('filesystems.offsite_backup_disk');
        $manifest = trim((string) $this->option('manifest'));
        if ($disk === '') {
            $this->error('RESAVAR_OFFSITE_BACKUP_DISK must be explicitly configured.');
            return self::FAILURE;
        }
        if ($this->option('restore-drill')
            && ($manifest === ''
                || $this->option('confirm-isolated') !== 'RESTORE_PRIVATE_MEDIA_TO_ISOLATED_ROOT')) {
            $this->error('Isolated media restore requires an archive manifest and explicit confirmation.');
            return self::FAILURE;
        }
        if (! $this->option('restore-drill') && filled($this->option('confirm-isolated'))) {
            $this->error('Restore confirmation is only valid for isolated restore drills.');
            return self::FAILURE;
        }

        try {
            if ($this->option('restore-drill')) {
                $result = $service->restoreDrill($disk, $manifest);
                $action = 'private_media.restore_drill';
            } elseif ($manifest !== '') {
                $result = $service->verify($disk, $manifest);
                $action = 'private_media.verified';
            } else {
                $result = $service->create($disk);
                $manifest = $result['manifest'];
                $action = 'private_media.offsite_backup_created';
            }

            AuditLog::record($action, null, [], [
                'file_count' => $result['files'],
                'total_bytes' => $result['bytes'],
                'offsite_disk' => $disk,
                'manifest' => $manifest,
            ]);

            $this->line(json_encode([
                'status' => 'verified',
                'operation' => $action,
                'manifest' => $manifest,
                'files' => $result['files'],
                'bytes' => $result['bytes'],
            ], JSON_THROW_ON_ERROR));
            $this->warn('Retain the APP_KEY independently and test real offsite recovery before production signoff.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            // A storage/crypt exception may contain object keys or credentials.
            Log::warning('Private media archive operation failed.', [
                'exception_class' => $error::class,
                'operation' => $this->option('restore-drill') ? 'restore' : 'archive_or_verify',
            ]);
            $this->error('Private media backup/verification failed safely. Check protected logs.');
            return self::FAILURE;
        }
    }
}

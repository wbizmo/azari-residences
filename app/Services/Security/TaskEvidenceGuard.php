<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaskEvidenceGuard
{
    public function __construct(private readonly BookingAttachmentScanner $scanner) {}

    /**
     * Store a task image only after malware scanner approval.
     * No evidence path is returned on a scanner outage or infected result.
     */
    public function store(?UploadedFile $image, int $taskId): ?array
    {
        if ($image === null) {
            return null;
        }

        $mime = (string) $image->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages([
                'evidence' => 'Evidence must be a JPEG or PNG image.',
            ]);
        }

        $path = $image->store('property-operations/evidence', 'private');
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'evidence' => 'Unable to store this evidence file.',
            ]);
        }

        try {
            if ($this->scanner->scan($path) !== 'clean') {
                throw ValidationException::withMessages([
                    'evidence' => 'Evidence failed the security scan.',
                ]);
            }
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($path);

            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);

            throw ValidationException::withMessages([
                'evidence' => 'Evidence scanning is temporarily unavailable. Retry later.',
            ]);
        }

        return [
            'evidence_path' => $path,
            'evidence_name' => 'task-evidence-'.$taskId.'.'.($mime === 'image/png' ? 'png' : 'jpg'),
            'evidence_mime' => $mime,
        ];
    }
}

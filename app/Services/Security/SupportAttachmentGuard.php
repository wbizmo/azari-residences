<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SupportAttachmentGuard
{
    public function __construct(private readonly BookingAttachmentScanner $scanner) {}

    /**
     * Quarantine support uploads until clamd returns an explicit clean decision.
     * A disconnected or unconfigured scanner must never publish an attachment.
     *
     * @return array{attachment_path: string|null, attachment_name: string|null}
     */
    public function store(?UploadedFile $file): array
    {
        if ($file === null) {
            return ['attachment_path' => null, 'attachment_name' => null];
        }

        $path = $file->store('support-attachments', 'private');
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['attachment' => 'Unable to store the attachment.']);
        }

        try {
            $decision = $this->scanner->scan($path);
            if ($decision !== 'clean') {
                throw ValidationException::withMessages(['attachment' => 'Attachment failed the security scan.']);
            }
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($path);
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);
            throw ValidationException::withMessages(['attachment' => 'Attachment scanning is temporarily unavailable. Please retry without a file or later.']);
        }

        return [
            'attachment_path' => $path,
            'attachment_name' => substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 180),
        ];
    }
}

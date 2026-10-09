<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

final class BookingAttachmentName
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    public static function fromUpload(UploadedFile $file): string
    {
        // Trust detected content type, not an attacker-provided extension.
        $mime = $file->getMimeType();
        abort_unless(isset(self::EXTENSIONS[$mime]), 422, 'Unsupported attachment content type.');

        $original = str_replace('\\', '/', $file->getClientOriginalName());
        $name = pathinfo(basename($original), PATHINFO_FILENAME);
        $stem = self::stem($name);

        return $stem.'.'.self::EXTENSIONS[$mime];
    }

    public static function forDownload(?string $name, ?string $storedPath = null): string
    {
        $original = str_replace('\\', '/', (string) ($name ?: 'attachment'));
        $base = basename($original);
        $extension = strtolower(pathinfo($base, PATHINFO_EXTENSION));

        if (! in_array($extension, self::EXTENSIONS, true)) {
            $extension = strtolower(pathinfo((string) $storedPath, PATHINFO_EXTENSION));
        }

        // Historical uploads can have ambiguous extensions. Never emit
        // untrusted or executable suffixes to clients.
        if (! in_array($extension, self::EXTENSIONS, true)) {
            $extension = 'bin';
        }

        return self::stem(pathinfo($base, PATHINFO_FILENAME)).'.'.$extension;
    }

    private static function stem(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?: '';
        $value = trim($value, '-_');

        return substr($value ?: 'attachment', 0, 80);
    }
}

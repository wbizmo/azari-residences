<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Authenticated, chunked offsite archive of all non-backup private files.
 * Neither filenames nor file contents are placed in cleartext offsite.
 * Restoration is ONLY to an explicitly configured, empty isolated root.
 */
class PrivateMediaRecoveryService
{
    private const BASE = 'resavar/private-media';
    private const FORMAT = 'resavar.private-media.v1';
    private const CHUNK_BYTES = 262144;

    /** @return array{manifest:string,files:int,bytes:int} */
    public function create(string $offsiteDisk): array
    {
        $this->assertOffsite($offsiteDisk);
        $source = Storage::disk('private');
        $target = Storage::disk($offsiteDisk);
        $prefix = self::BASE.'/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(8));
        $files = [];
        $bytes = 0;

        try {
            foreach ($source->allFiles() as $path) {
                if ($this->skipSourceFile($path)) {
                    continue;
                }

                $this->assertRelativePath($path);
                $reader = $source->readStream($path);
                if (! is_resource($reader)) {
                    throw new \RuntimeException('Cannot open private media for backup.');
                }

                $digest = hash_init('sha256');
                $size = 0;
                $parts = 0;
                $index = count($files);
                try {
                    while (! feof($reader)) {
                        $chunk = fread($reader, self::CHUNK_BYTES);
                        if ($chunk === false) {
                            throw new \RuntimeException('Private media read failed.');
                        }
                        if ($chunk === '') {
                            if (feof($reader)) {
                                break;
                            }
                            throw new \RuntimeException('Unexpected empty private media chunk.');
                        }

                        hash_update($digest, $chunk);
                        $size += strlen($chunk);
                        $key = $this->chunkKey($prefix, $index, $parts);
                        if (! $target->put($key, Crypt::encryptString(base64_encode($chunk)))) {
                            throw new \RuntimeException('Offsite media write failed.');
                        }
                        $parts++;
                    }
                } finally {
                    fclose($reader);
                }

                $files[] = [
                    'path' => $path,
                    'size' => $size,
                    'sha256' => hash_final($digest),
                    'parts' => $parts,
                ];
                $bytes += $size;
            }

            // Publication of the authenticated manifest happens LAST. A
            // failed upload without a manifest can never pass verification.
            $manifest = $prefix.'/manifest.rsvenc';
            $payload = json_encode([
                'format' => self::FORMAT,
                'created_at' => now()->toIso8601String(),
                'files' => $files,
            ], JSON_THROW_ON_ERROR);
            if (! $target->put($manifest, Crypt::encryptString($payload))) {
                throw new \RuntimeException('Cannot publish offsite media manifest.');
            }

            $verified = $this->verify($offsiteDisk, $manifest);
            if ($verified['files'] !== count($files) || $verified['bytes'] !== $bytes) {
                throw new \RuntimeException('Offsite media verification did not match the source.');
            }
            return ['manifest' => $manifest, 'files' => count($files), 'bytes' => $bytes];
        } catch (\Throwable $e) {
            // No partial archive is accepted. Best-effort cleanup of uploaded
            // chunks. The original private files are never mutated.
            try {
                $target->deleteDirectory($prefix);
            } catch (\Throwable) {
                // Scheduled remote lifecycle expiry handles orphaned chunks.
            }
            throw $e;
        }
    }

    /** @return array{files:int,bytes:int} */
    public function verify(string $offsiteDisk, string $manifest): array
    {
        $entries = $this->readManifest($offsiteDisk, $manifest);
        $target = Storage::disk($offsiteDisk);
        $prefix = substr($manifest, 0, -strlen('/manifest.rsvenc'));
        $bytes = 0;

        foreach ($entries as $index => $entry) {
            $digest = hash_init('sha256');
            $size = 0;
            for ($part = 0; $part < $entry['parts']; $part++) {
                $chunk = $this->decryptPart($target, $this->chunkKey($prefix, $index, $part));
                $size += strlen($chunk);
                hash_update($digest, $chunk);
            }
            if ($size !== $entry['size'] || ! hash_equals($entry['sha256'], hash_final($digest))) {
                throw new \RuntimeException('Offsite media integrity verification failed.');
            }
            $bytes += $size;
        }

        return ['files' => count($entries), 'bytes' => $bytes];
    }

    /** Restore only to an empty, explicitly configured isolated local root. */
    public function restoreDrill(string $offsiteDisk, string $manifest): array
    {
        $targetConfig = config('filesystems.disks.resavar_media_restore');
        $targetRoot = realpath((string) ($targetConfig['root'] ?? ''));
        $sourceRoot = realpath((string) config('filesystems.disks.private.root'));
        if (($targetConfig['driver'] ?? null) !== 'local'
            || ! $sourceRoot || ! $targetRoot
            || ! preg_match('/(?:restore|drill|isolat)/i', $targetRoot)
            || $targetRoot === $sourceRoot
            || str_starts_with($targetRoot, $sourceRoot.DIRECTORY_SEPARATOR)
            || str_starts_with($sourceRoot, $targetRoot.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('A separate, existing isolated media restore root is required.');
        }

        $destination = Storage::disk('resavar_media_restore');
        // A directory containing symlinks or empty subdirectories is not a
        // clean restore target either; reject any existing child entry.
        if (array_diff(scandir($targetRoot) ?: [], ['.', '..']) !== []) {
            throw new \RuntimeException('Isolated media restore target must be empty.');
        }

        // Run the full authenticated integrity pass BEFORE writing anything.
        $verified = $this->verify($offsiteDisk, $manifest);
        $entries = $this->readManifest($offsiteDisk, $manifest);
        $source = Storage::disk($offsiteDisk);
        $prefix = substr($manifest, 0, -strlen('/manifest.rsvenc'));

        foreach ($entries as $index => $entry) {
            $stream = fopen('php://temp/maxmemory:1048576', 'w+b');
            if (! is_resource($stream)) {
                throw new \RuntimeException('Isolated restore cannot create temporary file.');
            }
            try {
                for ($part = 0; $part < $entry['parts']; $part++) {
                    $chunk = $this->decryptPart($source, $this->chunkKey($prefix, $index, $part));
                    if (fwrite($stream, $chunk) !== strlen($chunk)) {
                        throw new \RuntimeException('Isolated media restore staging failed.');
                    }
                }
                rewind($stream);
                if (! $destination->put($entry['path'], $stream)) {
                    throw new \RuntimeException('Isolated media restore write failed.');
                }
            } finally {
                fclose($stream);
            }
        }

        foreach ($entries as $entry) {
            $reader = $destination->readStream($entry['path']);
            if (! is_resource($reader)) {
                throw new \RuntimeException('Restored media is missing.');
            }
            try {
                $hash = hash_init('sha256');
                $size = hash_update_stream($hash, $reader);
                if ($size !== $entry['size']
                    || ! hash_equals($entry['sha256'], hash_final($hash))) {
                    throw new \RuntimeException('Isolated restored media checksum mismatch.');
                }
            } finally {
                fclose($reader);
            }
        }

        return $verified;
    }

    /** @return list<array{path:string,size:int,sha256:string,parts:int}> */
    private function readManifest(string $disk, string $manifest): array
    {
        $this->assertOffsite($disk);
        if (! preg_match('~^resavar/private-media/[0-9]{8}-[0-9]{6}-[0-9a-f]{16}/manifest\.rsvenc$~', $manifest)) {
            throw new \RuntimeException('Invalid offsite archive manifest identifier.');
        }
        $storage = Storage::disk($disk);
        if (! $storage->exists($manifest)) {
            throw new \RuntimeException('Offsite media archive manifest not found.');
        }
        $data = json_decode(Crypt::decryptString($storage->get($manifest)), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['format'] ?? '') !== self::FORMAT
            || ! is_array($data['files'] ?? null) || ! array_is_list($data['files'])) {
            throw new \RuntimeException('Invalid authenticated media archive manifest.');
        }

        $seen = [];
        foreach ($data['files'] as $entry) {
            if (! is_array($entry) || ! is_string($entry['path'] ?? null)
                || ! is_int($entry['size'] ?? null) || ($entry['size'] < 0)
                || ! is_int($entry['parts'] ?? null) || $entry['parts'] < 0
                || ($entry['parts'] === 0 && $entry['size'] !== 0)
                || ($entry['parts'] > 0 && $entry['size'] === 0)
                || ! is_string($entry['sha256'] ?? null)
                || ! preg_match('/^[0-9a-f]{64}$/', $entry['sha256'])) {
                throw new \RuntimeException('Malformed private media archive entry.');
            }
            $this->assertRelativePath($entry['path']);
            if (isset($seen[$entry['path']])) {
                throw new \RuntimeException('Duplicate private media path.');
            }
            $seen[$entry['path']] = true;
        }
        return $data['files'];
    }

    private function assertOffsite(string $disk): void
    {
        if ($disk !== 'resavar_offsite'
            || ! is_array(config('filesystems.disks.resavar_offsite'))) {
            throw new \RuntimeException('Explicit independent offsite storage is required.');
        }
    }

    private function assertRelativePath(string $path): void
    {
        if ($path === '' || strlen($path) > 1024 || str_contains($path, '\\')
            || str_contains($path, "\0") || str_starts_with($path, '/')
            || in_array('', explode('/', $path), true)
            || in_array('..', explode('/', $path), true)
            || in_array('.', explode('/', $path), true)) {
            throw new \RuntimeException('Unsafe relative private media archive path.');
        }
    }

    private function skipSourceFile(string $path): bool
    {
        return str_starts_with($path, 'backups/')
            || str_starts_with($path, 'resavar/private-media/')
            || $path === 'healthcheck.tmp';
    }

    private function chunkKey(string $prefix, int $file, int $part): string
    {
        return sprintf('%s/chunks/%08d/%08d.rsvenc', $prefix, $file, $part);
    }

    private function decryptPart($disk, string $key): string
    {
        if (! $disk->exists($key)) {
            throw new \RuntimeException('Missing encrypted offsite media chunk.');
        }
        $plain = base64_decode(Crypt::decryptString($disk->get($key)), true);
        if ($plain === false) {
            throw new \RuntimeException('Invalid encrypted media chunk encoding.');
        }
        return $plain;
    }
}

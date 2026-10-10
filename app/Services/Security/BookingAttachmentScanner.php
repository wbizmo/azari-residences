<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BookingAttachmentScanner
{
    /**
     * Scan a private object using clamd INSTREAM. An unavailable scanner is
     * never interpreted as a clean file.
     *
     * @return 'clean'|'infected'
     */
    public function scan(string $path): string
    {
        $host = trim((string) config('booking_attachment_scanner.host'));
        if ($host === '') {
            throw new RuntimeException('Attachment scanner is not configured.');
        }

        $port = max(1, min(65535, (int) config('booking_attachment_scanner.port', 3310)));
        $timeout = max(2, min(30, (int) config('booking_attachment_scanner.timeout_seconds', 10)));
        $source = Storage::disk('private')->readStream($path);
        if (! is_resource($source)) {
            throw new RuntimeException('Private attachment could not be opened.');
        }

        $socket = @stream_socket_client(
            'tcp://'.$host.':'.$port, $errno, $error, $timeout, STREAM_CLIENT_CONNECT
        );

        if (! is_resource($socket)) {
            fclose($source);
            throw new RuntimeException('Attachment scanner is unreachable.');
        }

        try {
            stream_set_timeout($socket, $timeout);
            $this->writeAll($socket, "zINSTREAM\0");
            $bytes = 0;

            while (! feof($source)) {
                $chunk = fread($source, 65536);
                if ($chunk === false) {
                    throw new RuntimeException('Attachment read failed during scanning.');
                }
                if ($chunk === '') {
                    break;
                }

                $bytes += strlen($chunk);
                if ($bytes > 5 * 1024 * 1024) {
                    throw new RuntimeException('Attachment exceeds scanner size limit.');
                }
                $this->writeAll($socket, pack('N', strlen($chunk)).$chunk);
            }

            $this->writeAll($socket, pack('N', 0));
            $result = stream_get_line($socket, 4096, "\0");
            if (! is_string($result)) {
                throw new RuntimeException('Attachment scanner returned no decision.');
            }

            if (str_ends_with(trim($result), ' FOUND')) {
                return 'infected';
            }
            if (str_ends_with(trim($result), ' OK')) {
                return 'clean';
            }

            throw new RuntimeException('Attachment scanner did not return a recognized decision.');
        } finally {
            fclose($source);
            fclose($socket);
        }
    }

    /** Handle partial writes so clamd never receives a truncated chunk. */
    private function writeAll($socket, string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $written = fwrite($socket, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Attachment scanner connection interrupted.');
            }
            $offset += $written;
        }
    }
}

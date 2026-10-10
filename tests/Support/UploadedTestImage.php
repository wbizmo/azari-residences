<?php

namespace Tests\Support;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;

/** Real image bytes without the optional GD extension for file-upload tests. */
final class UploadedTestImage
{
    // A valid 1x1 JPEG is sufficient for MIME checks on proof attachments.
    private const JPEG = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDACgcHiMeGSgjISMtKygwPGRBPDc3PHtYXUlkkYCZlo+AjIqgtObDoKrarYqMyP/L2u71////m8H////6/+b9//j/2wBDASstLTw1PHZBQXb4pYyl+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAgP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCWAsD/2Q==';

    private static function pngChunk(string $name, string $content): string
    {
        return pack('N', strlen($content)).$name.$content.pack('N', crc32($name.$content));
    }

    private static function png(): string
    {
        $pixelRow = "\0".str_repeat("\0\0\0", 640);

        return "\x89PNG\r\n\x1a\n"
            .self::pngChunk('IHDR', pack('NNCCCCC', 640, 480, 8, 2, 0, 0, 0))
            .self::pngChunk('IDAT', gzcompress(str_repeat($pixelRow, 480), 9))
            .self::pngChunk('IEND', '');
    }

    public static function make(string $name): File
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            throw new \InvalidArgumentException('Expected a JPEG or PNG test filename.');
        }

        return UploadedFile::fake()->createWithContent(
            $name,
            $extension === 'png' ? self::png() : base64_decode(self::JPEG, true)
        );
    }
}

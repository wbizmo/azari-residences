<?php

namespace Tests\Feature\PhaseTwo;

use App\Support\BookingAttachmentName;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SafeBookingAttachmentNameTest extends TestCase
{
    public function test_untrusted_uploaded_filename_is_normalized_to_detected_content_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('../../passport<script>.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfZcAAAAASUVORK5CYII='));
        $this->assertSame('passport-script.png', BookingAttachmentName::fromUpload($file));
    }

    public function test_historical_download_filenames_cannot_include_path_or_executable_suffix(): void
    {
        $this->assertSame('report.pdf', BookingAttachmentName::forDownload('../../report.php', 'booking-messages/secure.pdf'));
        $this->assertSame('safe-file.png', BookingAttachmentName::forDownload('C:\\private\\safe file.png'));
        $this->assertSame('malware.bin', BookingAttachmentName::forDownload('malware.exe'));
    }
}

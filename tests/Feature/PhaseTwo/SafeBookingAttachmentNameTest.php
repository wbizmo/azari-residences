<?php

namespace Tests\Feature\PhaseTwo;

use App\Support\BookingAttachmentName;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SafeBookingAttachmentNameTest extends TestCase
{
    public function test_untrusted_uploaded_filename_is_normalized_to_detected_content_extension(): void
    {
        $file = UploadedFile::fake()->image('../../passport<script>.jpeg');
        $this->assertSame('passport-script.jpg', BookingAttachmentName::fromUpload($file));
    }

    public function test_historical_download_filenames_cannot_include_path_or_executable_suffix(): void
    {
        $this->assertSame('report.pdf', BookingAttachmentName::forDownload('../../report.php', 'booking-messages/secure.pdf'));
        $this->assertSame('safe-file.png', BookingAttachmentName::forDownload('C:\\private\\safe file.png'));
        $this->assertSame('malware.bin', BookingAttachmentName::forDownload('malware.exe'));
    }
}

<?php

namespace Tests\Feature\PhaseTwo;

use App\Support\BookingAttachmentName;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PrevalidatedMessageAttachmentTest extends TestCase
{
    public function test_unsupported_bytes_fail_before_storage_with_a_safe_error(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'resavar-invalid-');
        file_put_contents($path, 'plain text is not a JPEG image');
        try {
            // Real temporary file: finfo detects text/plain, regardless of
            // the filename supplied by the browser.
            $file = new UploadedFile($path, 'fake-photo.jpg', null, null, true);
            $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
            BookingAttachmentName::fromUpload($file);
        } finally {
            unlink($path);
        }
    }

    public function test_both_inbox_uploads_validate_content_before_storing_file(): void
    {
        foreach ([
            app_path('Http/Controllers/UserArea/PhaseTwoGuestController.php'),
            app_path('Http/Controllers/User/OwnerBookingMessageController.php'),
        ] as $path) {
            $body = file_get_contents($path);
            $check = strpos($body, 'BookingAttachmentName::fromUpload($file)');
            $store = strpos($body, "\$file?->store('booking-messages', 'private')");
            $this->assertNotFalse($check);
            $this->assertNotFalse($store);
            $this->assertLessThan($store, $check);
        }
    }
}

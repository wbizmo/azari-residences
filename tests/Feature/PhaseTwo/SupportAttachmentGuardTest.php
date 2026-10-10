<?php

namespace Tests\Feature\PhaseTwo;

use App\Services\Security\BookingAttachmentScanner;
use App\Services\Security\SupportAttachmentGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;
use Tests\Support\UploadedTestImage;

class SupportAttachmentGuardTest extends TestCase
{
    public function test_clean_upload_is_available_only_after_scan(): void
    {
        Storage::fake('private');
        $scanner = Mockery::mock(BookingAttachmentScanner::class);
        $scanner->shouldReceive('scan')->once()->andReturn('clean');

        $result = (new SupportAttachmentGuard($scanner))->store(UploadedTestImage::make('proof.png'));

        $this->assertNotNull($result['attachment_path']);
        $this->assertSame('proof.png', $result['attachment_name']);
        Storage::disk('private')->assertExists($result['attachment_path']);
    }

    public function test_infected_upload_is_removed(): void
    {
        Storage::fake('private');
        $scanner = Mockery::mock(BookingAttachmentScanner::class);
        $scanner->shouldReceive('scan')->once()->andReturn('infected');

        try {
            (new SupportAttachmentGuard($scanner))->store(UploadedTestImage::make('bad.png'));
            $this->fail('An infected attachment must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attachment', $exception->errors());
        }

        $this->assertEmpty(Storage::disk('private')->allFiles('support-attachments'));
    }

    public function test_scanner_failure_is_closed_and_object_is_removed(): void
    {
        Storage::fake('private');
        $scanner = Mockery::mock(BookingAttachmentScanner::class);
        $scanner->shouldReceive('scan')->once()->andThrow(new \RuntimeException('scanner unavailable'));

        $this->expectException(ValidationException::class);
        try {
            (new SupportAttachmentGuard($scanner))->store(UploadedTestImage::make('proof.png'));
        } finally {
            $this->assertEmpty(Storage::disk('private')->allFiles('support-attachments'));
        }
    }
}

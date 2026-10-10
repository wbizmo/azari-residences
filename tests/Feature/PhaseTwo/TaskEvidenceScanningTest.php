<?php

namespace Tests\Feature\PhaseTwo;

use App\Services\Security\BookingAttachmentScanner;
use App\Services\Security\TaskEvidenceGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class TaskEvidenceScanningTest extends TestCase
{
    public function test_clean_housekeeping_image_is_kept_only_after_a_clean_scan(): void
    {
        Storage::fake('private');
        $scanner = Mockery::mock(BookingAttachmentScanner::class);
        $scanner->shouldReceive('scan')->once()->andReturn('clean');

        $result = (new TaskEvidenceGuard($scanner))
            ->store(UploadedFile::fake()->image('proof.png'), 42);

        $this->assertSame('task-evidence-42.png', $result['evidence_name']);
        $this->assertSame('image/png', $result['evidence_mime']);
        Storage::disk('private')->assertExists($result['evidence_path']);
    }

    public function test_infected_image_is_deleted_without_publishing_a_task_path(): void
    {
        Storage::fake('private');
        $scanner = Mockery::mock(BookingAttachmentScanner::class);
        $scanner->shouldReceive('scan')->once()->andReturn('infected');

        try {
            (new TaskEvidenceGuard($scanner))
                ->store(UploadedFile::fake()->image('bad.png'), 42);
            $this->fail('Infected evidence must not be stored.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('evidence', $exception->errors());
        }

        $this->assertEmpty(Storage::disk('private')->allFiles('property-operations/evidence'));
    }

    public function test_scanner_outage_deletes_private_file_and_blocks_submission(): void
    {
        Storage::fake('private');
        $scanner = Mockery::mock(BookingAttachmentScanner::class);
        $scanner->shouldReceive('scan')->once()->andThrow(new \RuntimeException('scanner unavailable'));

        $this->expectException(ValidationException::class);
        try {
            (new TaskEvidenceGuard($scanner))
                ->store(UploadedFile::fake()->image('proof.jpg'), 42);
        } finally {
            $this->assertEmpty(Storage::disk('private')->allFiles('property-operations/evidence'));
        }
    }
}

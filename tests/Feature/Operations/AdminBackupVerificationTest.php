<?php

namespace Tests\Feature\Operations;

use App\Http\Controllers\Admin\OperationsController;
use App\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBackupVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_verifies_archive_on_private_disk_with_structural_decryption_check(): void
    {
        Storage::fake('private');
        $this->assertSame(0, Artisan::call('azari:backup', ['--verify' => true]));
        $backup = BackupRun::query()->latest('id')->firstOrFail();

        $this->assertTrue(Storage::disk('private')->exists($backup->path));
        $this->assertSame('verified', $backup->status);

        $response = app(OperationsController::class)->verifyBackup($backup);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('verified', $backup->fresh()->status);
        $this->assertNotNull($backup->fresh()->verified_at);
    }

    public function test_admin_rejects_corrupted_private_archive_even_if_a_record_was_previously_verified(): void
    {
        Storage::fake('private');
        $this->assertSame(0, Artisan::call('azari:backup', ['--verify' => true]));
        $backup = BackupRun::query()->latest('id')->firstOrFail();
        Storage::disk('private')->put($backup->path, 'damaged encrypted archive');

        $response = app(OperationsController::class)->verifyBackup($backup);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('verification_failed', $backup->fresh()->status);
        $this->assertNull($backup->fresh()->verified_at);
    }
}

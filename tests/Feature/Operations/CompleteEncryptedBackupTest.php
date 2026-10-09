<?php

namespace Tests\Feature\Operations;

use App\Models\BackupRun;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompleteEncryptedBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_database_tables_are_archived_and_sensitive_rows_are_encrypted(): void
    {
        Storage::fake('private');
        $user = User::factory()->create(['email' => 'sensitive-canary@example.test']);
        Booking::factory()->create(['user_id' => $user->getKey()]);

        $this->assertSame(0, Artisan::call('azari:backup', ['--verify' => true]));
        $run = BackupRun::query()->latest('id')->firstOrFail();
        $this->assertSame('verified', $run->status);
        $this->assertSame('resavar.encrypted.ndjson.v2', $run->metadata['format']);
        $archive = gzdecode(Storage::disk('private')->get($run->path));
        $this->assertNotFalse($archive);
        $this->assertStringContainsString('"name":"users"', $archive);
        $this->assertStringContainsString('"name":"inventory_dates"', $archive);
        $this->assertStringContainsString('"name":"bookings"', $archive);
        $this->assertStringContainsString('"name":"payments"', $archive);
        $this->assertStringNotContainsString('sensitive-canary@example.test', $archive);
        $this->assertSame(0, Artisan::call('azari:backup-restore-check', ['backupRun' => $run->getKey()]));
    }

    public function test_modified_archive_fails_integrity_validation(): void
    {
        Storage::fake('private');
        $this->assertSame(0, Artisan::call('azari:backup'));
        $run = BackupRun::query()->latest('id')->firstOrFail();
        Storage::disk('private')->put($run->path, 'tampered archive');
        $this->assertSame(1, Artisan::call('azari:backup-restore-check', ['backupRun' => $run->getKey()]));
    }
}

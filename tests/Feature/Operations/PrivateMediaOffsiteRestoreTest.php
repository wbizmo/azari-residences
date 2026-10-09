<?php

namespace Tests\Feature\Operations;

use App\Services\Operations\PrivateMediaRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateMediaOffsiteRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('resavar_offsite');
        Storage::fake('resavar_media_restore');
        config(['filesystems.offsite_backup_disk' => 'resavar_offsite']);
    }

    public function test_encrypted_offsite_archive_verifies_and_restores_only_into_isolated_root(): void
    {
        $private = Storage::disk('private');
        $private->put('identities/users/1/passport.pdf', "test identity \x00binary data");
        $private->put('agreements/37.pdf', 'private agreement test document');
        $private->put('backups/should-not-recurse.gz', 'excluded archive');
        $service = app(PrivateMediaRecoveryService::class);

        $result = $service->create('resavar_offsite');
        $this->assertSame(2, $result['files']);
        $this->assertEquals(
            strlen("test identity \x00binary data") + strlen('private agreement test document'),
            $result['bytes']
        );
        $this->assertSame(2, $service->verify('resavar_offsite', $result['manifest'])['files']);

        $offsite = Storage::disk('resavar_offsite');
        $this->assertStringNotContainsString(
            'passport.pdf', $offsite->get($result['manifest'])
        );
        $this->assertSame(2, $service->restoreDrill('resavar_offsite', $result['manifest'])['files']);
        $restored = Storage::disk('resavar_media_restore');
        $this->assertSame("test identity \x00binary data", $restored->get('identities/users/1/passport.pdf'));
        $this->assertSame('private agreement test document', $restored->get('agreements/37.pdf'));
        $this->assertFalse($restored->exists('backups/should-not-recurse.gz'));

        // No restore may overwrite an earlier rehearsal or an active media set.
        $this->expectException(\RuntimeException::class);
        $service->restoreDrill('resavar_offsite', $result['manifest']);
    }

    public function test_corrupt_offsite_chunk_is_rejected_and_never_restored(): void
    {
        Storage::disk('private')->put('identities/users/2/document.pdf', 'verified document');
        $service = app(PrivateMediaRecoveryService::class);
        $archive = $service->create('resavar_offsite');
        $prefix = substr($archive['manifest'], 0, -strlen('/manifest.rsvenc'));
        Storage::disk('resavar_offsite')->put(
            $prefix.'/chunks/00000000/00000000.rsvenc', 'broken ciphertext'
        );

        try {
            $service->restoreDrill('resavar_offsite', $archive['manifest']);
            $this->fail('A corrupted chunk must never be restored.');
        } catch (\Throwable) {
            $this->assertSame([], Storage::disk('resavar_media_restore')->allFiles());
        }
    }

    public function test_restore_requires_explicit_confirmation_and_configured_offsite_disk(): void
    {
        $this->assertSame(1, Artisan::call('resavar:backup-private-media', [
            '--manifest' => 'resavar/private-media/fake/manifest.rsvenc',
            '--restore-drill' => true,
        ]));
        config(['filesystems.offsite_backup_disk' => null]);
        $this->assertSame(1, Artisan::call('resavar:backup-private-media'));
    }

    public function test_local_private_disk_can_never_be_the_offsite_destination(): void
    {
        $this->expectException(\RuntimeException::class);
        app(PrivateMediaRecoveryService::class)->create('private');
    }
}

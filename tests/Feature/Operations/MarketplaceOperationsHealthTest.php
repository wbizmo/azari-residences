<?php

namespace Tests\Feature\Operations;

use App\Models\BackupRun;
use App\Models\SystemHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceOperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_closed_without_fresh_scheduler_queue_or_verified_backups(): void
    {
        $this->assertSame(1, Artisan::call('resavar:operations-health', ['--json' => true]));
        $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($output['healthy']);
        $this->assertFalse($output['checks']['verified_backup']);
    }

    public function test_refuses_missing_archives_even_when_database_claims_verified(): void
    {
        Storage::fake('private');
        foreach (['queue', 'scheduler'] as $component) {
            SystemHeartbeat::query()->create(['component' => $component, 'last_seen_at' => now()]);
        }
        BackupRun::query()->create([
            'driver' => 'sqlite', 'status' => 'verified', 'path' => 'backups/deleted',
            'checksum' => str_repeat('a', 64), 'size_bytes' => 100,
            'started_at' => now()->subMinute(), 'verified_at' => now(),
        ]);
        $this->assertSame(1, Artisan::call('resavar:operations-health', ['--json' => true]));
        $health = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($health['checks']['verified_backup']);
    }

    public function test_reports_green_with_recent_heartbeats_and_verified_backup(): void
    {
        foreach (['queue', 'scheduler'] as $component) {
            SystemHeartbeat::query()->create(['component' => $component, 'last_seen_at' => now()]);
        }
        Storage::fake('private');
        Storage::disk('private')->put('backups/test', 'encrypted-test-fixture');
        BackupRun::query()->create([
            'driver' => 'sqlite', 'status' => 'verified', 'path' => 'backups/test',
            'checksum' => hash('sha256', 'encrypted-test-fixture'),
            'size_bytes' => strlen('encrypted-test-fixture'),
            'started_at' => now()->subMinute(), 'verified_at' => now(),
        ]);
        $this->assertSame(0, Artisan::call('resavar:operations-health', ['--json' => true]));
        $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($output['healthy']);
        $this->assertTrue($output['checks']['queue']);
    }
}

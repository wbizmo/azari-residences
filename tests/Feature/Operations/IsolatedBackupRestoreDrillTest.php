<?php

namespace Tests\Feature\Operations;

use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IsolatedBackupRestoreDrillTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_refuses_to_run_without_explicit_confirmation(): void
    {
        $this->assertSame(1, Artisan::call('resavar:restore-drill', ['backupRun' => 1]));
    }

    public function test_restore_refuses_a_connection_pointing_to_the_active_database(): void
    {
        $active = config('database.default');
        config(['database.connections.resavar_restore' => config("database.connections.{$active}")]);

        $this->assertSame(1, Artisan::call('resavar:restore-drill', [
            'backupRun' => 1,
            '--confirm-isolated' => 'RESTORE_TO_ISOLATED_DATABASE',
        ]));
    }

    public function test_verified_archive_can_restore_real_rows_to_a_separate_empty_sqlite_database(): void
    {
        Storage::fake('private');
        $user = User::factory()->create(['email' => 'restore-canary@example.test']);
        $this->assertSame(0, Artisan::call('azari:backup', ['--verify' => true]));
        $run = BackupRun::query()->latest('id')->firstOrFail();

        $path = tempnam(sys_get_temp_dir(), 'resavar-restore-drill-');
        $this->assertNotFalse($path);

        config(['database.connections.resavar_restore' => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        try {
            DB::purge('resavar_restore');
            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => 'resavar_restore', '--force' => true,
            ]));

            // Default safety stays fail-closed: migrated target contains
            // permissions/site_settings defaults, and overwriting them needs
            // a second explicit operator acknowledgement.
            $this->assertSame(1, Artisan::call('resavar:restore-drill', [
                'backupRun' => $run->getKey(),
                '--confirm-isolated' => 'RESTORE_TO_ISOLATED_DATABASE',
            ]));
            $this->assertSame(0, Artisan::call('resavar:restore-drill', [
                'backupRun' => $run->getKey(),
                '--confirm-isolated' => 'RESTORE_TO_ISOLATED_DATABASE',
                '--replace-migration-seeds' => 'REPLACE_ONLY_MIGRATION_DEFAULTS',
            ]), Artisan::output());

            $this->assertSame(1, DB::connection('resavar_restore')
                ->table('users')->where('email', 'restore-canary@example.test')->count());
            $this->assertSame(
                DB::table('bookings')->count(),
                DB::connection('resavar_restore')->table('bookings')->count()
            );
            $this->assertSame(
                DB::table('payments')->count(),
                DB::connection('resavar_restore')->table('payments')->count()
            );

            // The target is no longer empty; replays must fail closed.
            $this->assertSame(1, Artisan::call('resavar:restore-drill', [
                'backupRun' => $run->getKey(),
                '--confirm-isolated' => 'RESTORE_TO_ISOLATED_DATABASE',
            ]));
        } finally {
            DB::disconnect('resavar_restore');
            DB::purge('resavar_restore');
            @unlink($path);
        }
    }
}

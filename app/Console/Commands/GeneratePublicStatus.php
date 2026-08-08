<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GeneratePublicStatus extends Command
{
    protected $signature = 'azari:status-snapshot';

    protected $description = 'Generate the sanitized public Azari system status snapshot';

    public function handle(): int
    {
        $path = (string) config('azari-status.snapshot_path');

        if ($path === '') {
            $this->error('Status snapshot path is not configured.');

            return self::FAILURE;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('Unable to prepare status snapshot directory.');

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | File-level mutex
        |--------------------------------------------------------------------------
        |
        | This deliberately does not depend on database/cache locks. If the
        | database or cache is experiencing an incident, the health collector
        | still needs to run and publish that degraded state.
        |
        */

        $lockHandle = fopen($directory.'/.collector.lock', 'c');

        if ($lockHandle === false) {
            return self::FAILURE;
        }

        if (! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);

            return self::SUCCESS;
        }

        try {
            return $this->generate($path);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function generate(string $path): int
    {
        $checkedAt = now()->utc();

        /*
        |--------------------------------------------------------------------------
        | Core probes
        |--------------------------------------------------------------------------
        */

        $database = $this->probe('data-services', function (): bool {
            DB::select('select 1');

            return true;
        });

        $cacheKey = 'azari-public-status-'.Str::uuid();

        $cache = $this->probe('cache-services', function () use ($cacheKey): bool {
            Cache::put($cacheKey, 'ok', 30);

            $result = Cache::get($cacheKey) === 'ok';

            Cache::forget($cacheKey);

            return $result;
        });

        $storageFile = 'status/.probe-'.Str::uuid().'.tmp';

        $storage = $this->probe('media-storage', function () use ($storageFile): bool {
            Storage::disk('private')->put($storageFile, 'ok');

            $result = Storage::disk('private')->exists($storageFile);

            Storage::disk('private')->delete($storageFile);

            return $result;
        });

        /*
        |--------------------------------------------------------------------------
        | Public web reachability
        |--------------------------------------------------------------------------
        |
        | URLs themselves are never written into the public snapshot.
        |
        */

        $configuredUrls = array_values(array_filter(
            (array) config('azari-status.public_urls', [])
        ));

        $webPassed = 0;
        $webAttempted = count($configuredUrls);

        foreach ($configuredUrls as $url) {
            $ok = $this->probe('public-web', function () use ($url): bool {
                $response = Http::timeout(6)
                    ->withHeaders([
                        'User-Agent' => 'Azari-System-Status/1.0',
                        'Accept' => 'text/html',
                    ])
                    ->get(rtrim($url, '/').'/');

                return $response->status() >= 200
                    && $response->status() < 400;
            });

            if ($ok) {
                $webPassed++;
            }
        }

        $webStatus = match (true) {
            $webAttempted === 0 => 'unknown',
            $webPassed === 0 => 'major_outage',
            $webPassed < $webAttempted => 'degraded',
            default => 'operational',
        };

        /*
        |--------------------------------------------------------------------------
        | Scheduler heartbeat
        |--------------------------------------------------------------------------
        */

        $schedulerLog = storage_path('logs/scheduler-cron.log');

        $schedulerFresh = false;

        if (is_file($schedulerLog)) {
            $mtime = @filemtime($schedulerLog);

            if ($mtime !== false) {
                $schedulerFresh = (time() - $mtime) <= 240;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Queue / background processing
        |--------------------------------------------------------------------------
        */

        $queueStatus = 'unknown';

        if ($database) {
            $queueStatus = $this->probe('background-processing', function (): bool {
                return Schema::hasTable('jobs')
                    && Schema::hasTable('failed_jobs');
            })
                ? 'operational'
                : 'degraded';

            if ($queueStatus === 'operational') {
                try {
                    $queued = (int) DB::table('jobs')->count();

                    $recentFailed = 0;

                    if (
                        Schema::hasTable('failed_jobs')
                        && Schema::hasColumn('failed_jobs', 'failed_at')
                    ) {
                        $recentFailed = (int) DB::table('failed_jobs')
                            ->where('failed_at', '>=', now()->subDay())
                            ->count();
                    }

                    $oldestAvailableAt = Schema::hasColumn('jobs', 'available_at')
                        ? DB::table('jobs')->min('available_at')
                        : null;

                    $stalled = is_numeric($oldestAvailableAt)
                        && (int) $oldestAvailableAt <= time()
                        && (time() - (int) $oldestAvailableAt) > 900;

                    if (
                        $queued > 100
                        || $recentFailed >= 5
                        || $stalled
                    ) {
                        $queueStatus = 'degraded';
                    }
                } catch (Throwable $e) {
                    $this->recordProbeFailure(
                        'background-processing',
                        $e
                    );

                    $queueStatus = 'degraded';
                }
            }
        } else {
            $queueStatus = 'partial_outage';
        }

        if (! $schedulerFresh && $queueStatus === 'operational') {
            $queueStatus = 'degraded';
        }

        /*
        |--------------------------------------------------------------------------
        | Application feature readiness
        |--------------------------------------------------------------------------
        */

        $bookingReady = $database
            && Route::has('availability.index');

        $accountsReady = $database
            && Route::has('login');

        $paymentsStatus = (
            $database
            && $schedulerFresh
            && in_array($queueStatus, ['operational', 'degraded'], true)
        )
            ? ($queueStatus === 'operational'
                ? 'operational'
                : 'degraded')
            : 'degraded';

        $notificationsStatus = (
            $schedulerFresh
            && config('queue.default') !== 'sync'
            && config('mail.default') !== 'log'
            && in_array($queueStatus, ['operational', 'degraded'], true)
        )
            ? ($queueStatus === 'operational'
                ? 'operational'
                : 'degraded')
            : 'degraded';

        /*
        |--------------------------------------------------------------------------
        | Static web application assets
        |--------------------------------------------------------------------------
        */

        $assetFiles = [
            [
                base_path('manifest.webmanifest'),
                public_path('manifest.webmanifest'),
            ],
            [
                base_path('service-worker.js'),
                public_path('service-worker.js'),
            ],
            [
                base_path('pwa-install.js'),
                public_path('pwa-install.js'),
            ],
        ];

        $assetsReady = true;

        foreach ($assetFiles as $alternatives) {
            $found = false;

            foreach ($alternatives as $candidate) {
                if (is_file($candidate)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $assetsReady = false;
                break;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Public-safe component model
        |--------------------------------------------------------------------------
        */

        $components = [
            [
                'key' => 'web',
                'name' => 'Web Experience',
                'description' => 'Public site access and core page delivery.',
                'status' => $webStatus,
            ],
            [
                'key' => 'booking',
                'name' => 'Booking & Availability',
                'description' => 'Reservation search and availability services.',
                'status' => $bookingReady
                    ? 'operational'
                    : 'partial_outage',
            ],
            [
                'key' => 'accounts',
                'name' => 'Accounts & Sign-in',
                'description' => 'Guest authentication and account access.',
                'status' => $accountsReady
                    ? 'operational'
                    : 'partial_outage',
            ],
            [
                'key' => 'payments',
                'name' => 'Payment Processing',
                'description' => 'Payment workflow and transaction processing.',
                'status' => $paymentsStatus,
            ],
            [
                'key' => 'notifications',
                'name' => 'Notifications',
                'description' => 'Automated guest communication processing.',
                'status' => $notificationsStatus,
            ],
            [
                'key' => 'background',
                'name' => 'Background Processing',
                'description' => 'Asynchronous application processing.',
                'status' => $queueStatus,
            ],
            [
                'key' => 'data',
                'name' => 'Data Services',
                'description' => 'Core application data availability.',
                'status' => $database
                    ? 'operational'
                    : 'major_outage',
            ],
            [
                'key' => 'cache',
                'name' => 'Session & Cache Services',
                'description' => 'Temporary application state and acceleration.',
                'status' => $cache
                    ? 'operational'
                    : 'degraded',
            ],
            [
                'key' => 'storage',
                'name' => 'Media & Documents',
                'description' => 'Application file and document storage.',
                'status' => $storage
                    ? 'operational'
                    : 'partial_outage',
            ],
            [
                'key' => 'scheduler',
                'name' => 'Scheduled Operations',
                'description' => 'Recurring automated platform operations.',
                'status' => $schedulerFresh
                    ? 'operational'
                    : 'degraded',
            ],
            [
                'key' => 'assets',
                'name' => 'Web Application Assets',
                'description' => 'Installable web application and static assets.',
                'status' => $assetsReady
                    ? 'operational'
                    : 'degraded',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Public overall status
        |--------------------------------------------------------------------------
        |
        | Only customer-impacting service components determine the public
        | platform headline. Supporting operational indicators remain visible
        | individually but cannot make the entire platform appear degraded
        | when guest-facing services are functioning normally.
        |
        */

        $headlineKeys = [
            'web',
            'booking',
            'accounts',
            'payments',
            'notifications',
            'background',
            'data',
            'storage',
        ];

        $headlineComponents = array_values(array_filter(
            $components,
            static fn (array $component): bool => in_array(
                $component['key'] ?? '',
                $headlineKeys,
                true
            )
        ));

        $overall = $this->overallStatus($headlineComponents);

        /*
        |--------------------------------------------------------------------------
        | Safe 24-hour history
        |--------------------------------------------------------------------------
        */

        $previous = $this->readExistingSnapshot($path);

        $history = is_array($previous['history'] ?? null)
            ? $previous['history']
            : [];

        $history[] = [
            'at' => $checkedAt->toIso8601String(),
            'overall' => $overall,
            'components' => array_map(
                static fn (array $component): array => [
                    'key' => $component['key'],
                    'status' => $component['status'],
                ],
                $components
            ),
        ];

        $historyLimit = max(
            12,
            (int) config('azari-status.history_samples', 288)
        );

        $history = array_slice(
            $history,
            -$historyLimit
        );

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Nothing below contains:
        | - provider names
        | - hosts
        | - database details
        | - connection names
        | - IP addresses
        | - server paths
        | - error messages
        | - credentials
        | - application versions
        | - raw failure counts
        |
        */

        $snapshot = [
            'schema' => 1,
            'checked_at' => $checkedAt->toIso8601String(),
            'overall' => $overall,
            'components' => $components,
            'history' => $history,
            'notice' => 'Automated checks run every five minutes. Displayed status may be delayed by up to five minutes.',
        ];

        $temporary = $path.'.tmp';

        $encoded = json_encode(
            $snapshot,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($temporary, $encoded, LOCK_EX) === false) {
            return self::FAILURE;
        }

        @chmod($temporary, 0640);

        if (! rename($temporary, $path)) {
            @unlink($temporary);

            return self::FAILURE;
        }

        @chmod($path, 0640);

        $this->info(
            'Public system status snapshot updated: '.$overall
        );

        return self::SUCCESS;
    }

    private function probe(string $name, callable $callback): bool
    {
        try {
            return (bool) $callback();
        } catch (Throwable $e) {
            $this->recordProbeFailure($name, $e);

            return false;
        }
    }

    private function recordProbeFailure(
        string $name,
        Throwable $exception
    ): void {
        /*
        | Do not log exception messages here: connection exceptions may
        | contain internal hostnames or endpoint details.
        */

        Log::warning('Public status probe failed.', [
            'probe' => $name,
            'exception_class' => $exception::class,
        ]);
    }

    private function overallStatus(array $components): string
    {
        $statuses = array_column(
            $components,
            'status'
        );

        if (in_array('major_outage', $statuses, true)) {
            return 'major_outage';
        }

        if (in_array('partial_outage', $statuses, true)) {
            return 'partial_outage';
        }

        if (
            in_array('degraded', $statuses, true)
            || in_array('unknown', $statuses, true)
        ) {
            return 'degraded';
        }

        return 'operational';
    }

    private function readExistingSnapshot(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        try {
            $decoded = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            return is_array($decoded)
                ? $decoded
                : [];
        } catch (Throwable) {
            return [];
        }
    }
}

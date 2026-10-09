<?php
/**
 * LOCAL ONLY MySQL checkout load profile. Synthetic data, no gateway calls.
 *
 * APP_ENV=testing DB_CONNECTION=mysql MAIL_MAILER=array \
 * RESAVAR_LOCAL_LOAD_WORKERS=12 php tests/local/phase1-checkout-load.php
 */
declare(strict_types=1);

use App\Models\AccommodationType;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\BookingCreationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$db = (string) DB::connection()->getDatabaseName();
$driver = (string) DB::connection()->getDriverName();
$n = (int) (getenv('RESAVAR_LOCAL_LOAD_WORKERS') ?: 12);
if (! app()->environment('testing')
    || ! in_array($driver, ['mysql', 'mariadb'], true)
    || ! preg_match('/(?:test|sandbox|isolat)/i', $db)
    || ! function_exists('pcntl_fork')
    || $n < 4 || $n > 24
    || (string) config('mail.default') !== 'array') {
    fwrite(STDERR, "Refused: disposable MySQL, APP_ENV=testing, MAIL_MAILER=array, pcntl and 4-24 workers required.\n");
    exit(2);
}

$actor = User::factory()->create();
$start = CarbonImmutable::today()->addDays(35);
$end = $start->addDays(2);
$requests = [];
$firstType = null;

for ($i = 0; $i < $n; $i++) {
    $property = Property::factory()->create([
        'status' => 'available', 'is_published' => true, 'same_day_booking' => true,
    ]);
    $type = $property->accommodationTypes()->firstOrFail();
    $type->update(['total_inventory' => 1]);
    $hold = app(AzariAvailabilityEngine::class)->hold(
        $property, $start, $end, 1, 0, 1, $actor->getKey(), $type->getKey()
    );
    $firstType ??= $type->getKey();
    $requests[] = [$hold->token, (int) $actor->getKey()];
}

$id = bin2hex(random_bytes(12));
$dir = sys_get_temp_dir().'/phase1-load-'.$id;
if (! mkdir($dir, 0700) && ! is_dir($dir)) {
    throw new RuntimeException('Cannot create isolated load probe directory.');
}
$flag = $dir.'/START';
$children = [];
try {
    for ($i = 0; $i < $n; $i++) {
        $pid = pcntl_fork();
        if ($pid < 0) {
            throw new RuntimeException('Cannot create isolated load worker.');
        }
        if ($pid === 0) {
            DB::purge(DB::getDefaultConnection());
            DB::reconnect();
            $until = microtime(true) + 15;
            while (! is_file($flag) && microtime(true) < $until) {
                usleep(2500);
            }
            $result = ['worker' => $i, 'ok' => false, 'queries' => 0, 'elapsed_ms' => null];
            if (is_file($flag)) {
                try {
                    $queries = 0;
                    DB::listen(static function () use (&$queries): void { $queries++; });
                    [$token, $userId] = $requests[$i];
                    $request = Request::create('/booking', 'POST', [
                        'hold_token' => $token,
                        'first_name' => 'Load',
                        'last_name' => 'Fixture',
                        'guest_email' => 'local-load@example.test',
                        'guest_phone' => '+2348000000000',
                        'nationality' => 'Nigerian',
                        'address' => '1 Test Street',
                        'city' => 'Lagos',
                        'country' => 'Nigeria',
                        'adults' => [['first_name' => 'Load', 'last_name' => 'Fixture']],
                        'children' => [],
                        'terms' => '1',
                    ]);
                    $request->setUserResolver(fn () => User::query()->findOrFail($userId));
                    $started = hrtime(true);
                    $booking = app(BookingCreationService::class)->create($request);
                    $elapsed = round((hrtime(true) - $started) / 1e6, 3);
                    $result = [
                        'worker' => $i,
                        'ok' => $booking->hold_token === $token,
                        'queries' => $queries,
                        'elapsed_ms' => $elapsed,
                    ];
                } catch (Throwable $e) {
                    $result['error_class'] = $e::class; // No PII or exception messages.
                }
            }
            file_put_contents($dir.'/'.$i.'.json', json_encode($result, JSON_THROW_ON_ERROR));
            exit($result['ok'] ? 0 : 1);
        }
        $children[] = $pid;
    }
    touch($flag);
    $exits = [];
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $exits[] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 3;
    }

    $latencies = [];
    $queryCounts = [];
    foreach (range(0, $n - 1) as $i) {
        $file = $dir.'/'.$i.'.json';
        $row = is_file($file) ? json_decode(file_get_contents($file), true) : null;
        if (! is_array($row) || ! ($row['ok'] ?? false)) {
            throw new RuntimeException('A concurrent synthetic checkout failed; see exit codes.');
        }
        $latencies[] = $row['elapsed_ms'];
        $queryCounts[] = $row['queries'];
    }

    sort($latencies, SORT_NUMERIC);
    sort($queryCounts, SORT_NUMERIC);
    $p = static fn (array $values, float $percent) => $values[(int) ceil(count($values) * $percent) - 1];
    $explainInventory = DB::select(
        'EXPLAIN SELECT id FROM inventory_dates WHERE accommodation_type_id = ? AND date >= ? AND date < ?',
        [$firstType, $start->toDateString(), $end->toDateString()]
    );
    $explainBooking = DB::select(
        'EXPLAIN SELECT id FROM bookings WHERE property_id = ? AND check_in < ? AND check_out > ?',
        [$property->getKey(), $end->toDateString(), $start->toDateString()]
    );
    echo json_encode([
        'environment' => 'testing',
        'database_driver' => $driver,
        'concurrent_checkouts' => $n,
        'successful_checkouts' => count($latencies),
        'checkout_latency_ms' => [
            'p50' => $p($latencies, 0.5),
            'p95' => $p($latencies, 0.95),
            'p99' => $p($latencies, 0.99),
        ],
        'db_queries' => ['p50' => $p($queryCounts, 0.5), 'p95' => $p($queryCounts, 0.95), 'p99' => $p($queryCounts, 0.99)],
        'inventory_explain' => $explainInventory,
        'booking_explain' => $explainBooking,
        'worker_exit_codes' => $exits,
        'note' => 'Synthetic service-layer load only. Do not claim production capacity without real traffic, payment gateway latency and measured infrastructure limits.',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Checkout load driver failed: '.$error::class.PHP_EOL);
    exit(1);
} finally {
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $ignored, WNOHANG);
    }
    foreach (glob($dir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($dir);
}

<?php
/**
 * Standalone LOCAL ONLY MySQL contention probe. Run against a disposable,
 * freshly migrated database named *test*, *sandbox* or *isolat*:
 *
 * APP_ENV=testing DB_CONNECTION=mysql php tests/local/phase1-mysql-contention.php
 *
 * Creates only synthetic data and NEVER connects to payment providers.
 */
declare(strict_types=1);

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\BookingCreationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$name = (string) DB::connection()->getDatabaseName();
$driver = (string) DB::connection()->getDriverName();
if (! app()->environment('testing')
    || ! in_array($driver, ['mysql', 'mariadb'], true)
    || ! preg_match('/(?:test|sandbox|isolat)/i', $name)
    || ! function_exists('pcntl_fork')) {
    fwrite(STDERR, "Refused: requires APP_ENV=testing, disposable named MySQL test/sandbox DB and pcntl.\n");
    exit(2);
}
$workers = 6;
$start = CarbonImmutable::today()->addDays(35);
$end = $start->addDays(2);

/** Run forked workers simultaneously with their own MySQL connections. */
function runParallel(int $workers, callable $callback): array
{
    $signal = tempnam(sys_get_temp_dir(), 'rsv-hold-start-');
    unlink($signal);
    $children = [];
    try {
        for ($i = 0; $i < $workers; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('Cannot fork isolated booking worker.');
            }
            if ($pid === 0) {
                // Never share an inherited PDO socket across forked processes.
                DB::purge(DB::getDefaultConnection());
                DB::reconnect();
                $until = microtime(true) + 10;
                while (! file_exists($signal) && microtime(true) < $until) {
                    usleep(2500);
                }
                if (! file_exists($signal)) {
                    exit(3);
                }
                try {
                    exit($callback($i));
                } catch (ValidationException) {
                    exit(1); // Expected rejection for oversold holds.
                } catch (Throwable $e) {
                    fwrite(STDERR, get_class($e)."\n");
                    exit(3);
                }
            }
            $children[] = $pid;
        }
        touch($signal);
        $results = [];
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $results[] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 3;
        }
        return $results;
    } finally {
        @unlink($signal);
        foreach ($children as $pid) {
            // All children should have terminated. Do not leave orphans.
            if (function_exists('pcntl_waitpid')) {
                pcntl_waitpid($pid, $ignored, WNOHANG);
            }
        }
        DB::purge(DB::getDefaultConnection());
        DB::reconnect();
    }
}

$case1 = Property::factory()->create(['status' => 'available', 'is_published' => true]);
$type1 = $case1->accommodationTypes()->firstOrFail();
$type1->update(['total_inventory' => 1]);
$quantityIds = [$case1->id, $type1->id];
$holdResults = runParallel($workers, function () use ($quantityIds, $start, $end): int {
    $property = Property::query()->findOrFail($quantityIds[0]);
    app(AzariAvailabilityEngine::class)->hold($property, $start, $end, 1, 0, 1, null, $quantityIds[1]);
    return 0;
});
$holds = BookingHold::query()->where('property_id', $case1->id)->active()->count();
if ($holds !== 1 || count(array_filter($holdResults, fn ($code) => $code === 0)) !== 1
    || in_array(3, $holdResults, true)) {
    fwrite(STDERR, "FAILED simultaneous last-room holds: ".json_encode($holdResults)."\n");
    exit(1);
}

$case2 = Property::factory()->create(['status' => 'available', 'is_published' => true]);
$type2 = $case2->accommodationTypes()->firstOrFail();
$type2->update(['total_inventory' => 1]);
$user = User::factory()->create();
$held = app(AzariAvailabilityEngine::class)->hold(
    $case2, $start, $end, 1, 0, 1, $user->getKey(), $type2->getKey()
);
$token = $held->token;
$uid = $user->getKey();

$bookingResults = runParallel($workers, function () use ($uid, $token): int {
    $actor = User::query()->findOrFail($uid);
    $request = Request::create('/booking', 'POST', [
        'hold_token' => $token,
        'first_name' => 'Ada',
        'last_name' => 'Okoro',
        'guest_email' => 'ada@example.test',
        'guest_phone' => '+2348000000000',
        'nationality' => 'Nigerian',
        'address' => '1 Test Street',
        'city' => 'Lagos',
        'country' => 'Nigeria',
        'adults' => [['first_name' => 'Ada', 'last_name' => 'Okoro']],
        'children' => [],
        'terms' => '1',
    ]);
    $request->setUserResolver(fn () => $actor);
    $booking = app(BookingCreationService::class)->create($request);
    return $booking->hold_token === $token ? 0 : 3;
});

$bookings = Booking::query()->where('hold_token', $token)->get();
$pass = $bookings->count() === 1
    && (int) $bookings->first()->rooms === 1
    && BookingHold::query()->where('token', $token)->doesntExist()
    && count(array_filter($bookingResults, fn ($code) => $code === 0)) === $workers;

echo json_encode([
    'driver' => $driver,
    'database' => $name,
    'workers' => $workers,
    'last_room_hold_results' => $holdResults,
    'confirmed_active_holds' => $holds,
    'single_booking_replay_results' => $bookingResults,
    'unique_bookings_for_consumed_hold' => $bookings->count(),
    'holds_consumed' => BookingHold::query()->where('token', $token)->doesntExist(),
    'passed' => $pass,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";

exit($pass ? 0 : 1);

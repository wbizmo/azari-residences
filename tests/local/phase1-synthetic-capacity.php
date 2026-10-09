<?php
/**
 * LOCAL ONLY, repeatable synthetic capacity dataset. NEVER live data.
 *
 * APP_ENV=testing DB_CONNECTION=mysql RESAVAR_ALLOW_SYNTHETIC_SEED=I_ACCEPT_DISPOSABLE_DB \
 * RESAVAR_SYNTH_LISTINGS=1000 RESAVAR_SYNTH_BOOKINGS=10000 \
 * php tests/local/phase1-synthetic-capacity.php
 *
 * For a larger operator-owned load test, scale to 10k or 100k listings and
 * 1m bookings only after checking isolated DB capacity/disk. No providers
 * or external webhooks are contacted.
 */
declare(strict_types=1);

use App\Models\Booking;
use App\Models\InventoryDate;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) DB::connection()->getDatabaseName();
$driver = (string) DB::connection()->getDriverName();
$listings = (int) (getenv('RESAVAR_SYNTH_LISTINGS') ?: 10);
$bookings = (int) (getenv('RESAVAR_SYNTH_BOOKINGS') ?: 100);
$days = (int) (getenv('RESAVAR_SYNTH_DAYS') ?: 365);

if (! app()->environment('testing')
    || ! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)
    || $database === ':memory:'
    || ! preg_match('/(?:test|sandbox|isolat)/i', $database)
    || getenv('RESAVAR_ALLOW_SYNTHETIC_SEED') !== 'I_ACCEPT_DISPOSABLE_DB'
    || $listings < 1 || $listings > 100000
    || $bookings < 0 || $bookings > 1000000
    || ! in_array($days, [7, 30, 365], true)) {
    fwrite(STDERR, "Refused: testing-only disposable DB, explicit confirmation, bounded dataset required.\n");
    exit(2);
}

$from = CarbonImmutable::today()->addDays(30);
$started = microtime(true);
$propertyIds = [];
$types = [];
$seedActor = User::factory()->create(['name' => 'Synthetic Local Capacity Actor']);

for ($i = 0; $i < $listings; $i++) {
    $property = Property::factory()->create([
        'status' => 'available',
        'is_published' => true,
        'name' => sprintf('Local Capacity Test Listing %06d', $i),
    ]);
    $type = $property->accommodationTypes()->firstOrFail();
    $type->update(['total_inventory' => 8]);

    $propertyIds[] = (int) $property->getKey();
    $types[] = (int) $type->getKey();

    $batch = [];
    for ($night = 0; $night < $days; $night++) {
        $batch[] = [
            'accommodation_type_id' => $type->getKey(),
            'date' => $from->addDays($night)->toDateString(),
            'sellable_inventory' => 8,
            'maintenance_inventory' => 0,
            'stop_sell' => false,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (count($batch) === 250) {
            DB::table('inventory_dates')->insertOrIgnore($batch);
            $batch = [];
        }
    }
    if ($batch !== []) {
        DB::table('inventory_dates')->insertOrIgnore($batch);
    }
}

for ($i = 0; $i < $bookings; $i++) {
    $index = $i % $listings;
    $night = intdiv($i, $listings) % max(1, $days - 3);
    $start = $from->addDays($night);
    Booking::factory()->create([
        'property_id' => $propertyIds[$index],
        'accommodation_type_id' => $types[$index],
        'user_id' => $seedActor->getKey(),
        // Historical/expired bookings exercise range indexes without
        // unintentionally reducing live sellable inventory in the fixture.
        'status' => 'completed',
        'rooms' => 1,
        'check_in' => $start,
        'check_out' => $start->addDays(2),
    ]);
}

$duration = round(microtime(true) - $started, 2);
echo json_encode([
    'environment' => 'testing',
    'database_driver' => $driver,
    'synthetic_listings_created' => $listings,
    'night_horizon' => $days,
    'inventory_date_rows_attempted' => $listings * $days,
    'synthetic_historical_bookings_created' => $bookings,
    'duration_seconds' => $duration,
    'next_steps' => [
        'Run resavar:benchmark-inventory <a freshly created accommodation type> --runs=20 --json',
        'Run tests/local/phase1-mysql-contention.php in a separate empty test database',
        'Collect EXPLAIN plans, query counts and warm/cold p50/p95/p99 under operator-owned staging loads',
    ],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";

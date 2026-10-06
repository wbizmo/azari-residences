<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\Location;
use App\Models\Property;
use App\Models\SystemHeartbeat;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Channels\ChannelAvailabilityService;
use App\Services\Channels\ChannelSyncService;
use App\Services\Channels\ICalChannelAdapter;
use App\Support\Money;
use App\Support\ResponsiveImage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResarvaFinalHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_snapshot_is_idempotent_and_missing_events_cancel_safely(): void
    {
        [$property, $type] = $this->propertyWithInventory(2);
        $connection = ChannelConnection::query()->create([
            'property_id' => $property->id,
            'accommodation_type_id' => $type->id,
            'provider' => 'ical',
            'name' => 'External calendar',
            'import_url' => 'https://example.com/calendar.ics',
            'is_active' => true,
            'fail_closed' => true,
            'stale_after_minutes' => 180,
            'last_successful_sync_at' => now(),
        ]);

        $ical = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:stay-1@example
DTSTART;VALUE=DATE:20261101
DTEND;VALUE=DATE:20261104
SUMMARY:Reserved
END:VEVENT
BEGIN:VEVENT
UID:stay-2@example
DTSTART;VALUE=DATE:20261110
DTEND;VALUE=DATE:20261112
SUMMARY:Reserved
END:VEVENT
END:VCALENDAR
ICS;

        $events = app(ICalChannelAdapter::class)->parse($ical);
        $sync = app(ChannelSyncService::class);

        $first = $sync->applySnapshot($connection, $events);
        $second = $sync->applySnapshot($connection, $events);
        $third = $sync->applySnapshot($connection, [$events[0]]);

        $this->assertSame(2, $first['imported']);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(2, ChannelReservation::query()->count());
        $this->assertSame(1, $third['cancelled']);
        $this->assertDatabaseHas('channel_reservations', ['external_id' => 'stay-2@example', 'status' => 'cancelled']);
    }

    public function test_external_inventory_is_subtracted_and_stale_fail_closed_connections_block_sales(): void
    {
        [$property, $type] = $this->propertyWithInventory(2);
        $in = CarbonImmutable::today()->addDays(20);
        $out = $in->addDays(2);
        $connection = ChannelConnection::query()->create([
            'property_id' => $property->id,
            'accommodation_type_id' => $type->id,
            'provider' => 'ical',
            'name' => 'External calendar',
            'import_url' => 'https://example.com/calendar.ics',
            'is_active' => true,
            'fail_closed' => true,
            'stale_after_minutes' => 180,
            'last_successful_sync_at' => now(),
        ]);

        ChannelReservation::query()->create([
            'channel_connection_id' => $connection->id,
            'property_id' => $property->id,
            'accommodation_type_id' => $type->id,
            'external_id' => 'external-1',
            'status' => 'active',
            'starts_on' => $in,
            'ends_on' => $out,
            'quantity' => 1,
        ]);

        $engine = app(AzariAvailabilityEngine::class);
        $this->assertSame(1, $engine->availableQuantity($type, $in, $out));

        $connection->update(['last_successful_sync_at' => now()->subHours(4)]);
        $this->assertTrue(app(ChannelAvailabilityService::class)->isStale($connection->fresh()));
        $this->assertSame(0, $engine->availableQuantity($type, $in, $out));
    }

    public function test_property_local_timezone_controls_booking_date_boundaries(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 23:30:00', 'UTC'));
        try {
            [$eastProperty, $eastType] = $this->propertyWithInventory(1, 'Pacific/Kiritimati');
            $engine = app(AzariAvailabilityEngine::class);

            try {
                $engine->assertRules(
                    $eastProperty,
                    CarbonImmutable::parse('2026-10-06'),
                    CarbonImmutable::parse('2026-10-07'),
                    1,
                    0,
                    1,
                    $eastType
                );
                $this->fail('October 6 is already in the past in the property timezone.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('check_in', $exception->errors());
            }

            [$westProperty, $westType] = $this->propertyWithInventory(1, 'Pacific/Honolulu');
            $engine->assertRules(
                $westProperty,
                CarbonImmutable::parse('2026-10-06'),
                CarbonImmutable::parse('2026-10-07'),
                1,
                0,
                1,
                $westType
            );
            $this->assertTrue(true);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_legacy_ngn_and_usd_records_remain_explicit_and_money_formatting_is_centralized(): void
    {
        $ngn = Property::factory()->create(['currency' => 'NGN', 'timezone' => 'Africa/Lagos']);
        $usd = Property::factory()->create(['currency' => 'USD', 'timezone' => 'America/New_York']);

        $ngn->update(['name' => 'Naira Stay']);
        $usd->update(['name' => 'Dollar Stay']);

        $this->assertSame('NGN', $ngn->fresh()->currency);
        $this->assertSame('USD', $usd->fresh()->currency);
        $this->assertStringContainsString('NGN', preg_replace('/[^A-Z]/', '', Money::format(1500, 'NGN', 'en')) ?: '');
        $this->assertNotSame(Money::format(1500, 'NGN', 'en'), Money::format(1500, 'USD', 'en'));
    }

    public function test_destination_pages_are_canonical_and_filtered_search_templates_are_noindex(): void
    {
        $location = Location::factory()->create(['name' => 'Lagos Island', 'slug' => 'lagos-island']);
        Property::factory()->create(['location_id' => $location->id, 'is_published' => true, 'status' => 'available']);

        $this->get(route('destinations.show', $location))
            ->assertOk()
            ->assertSee('Lagos Island')
            ->assertSee('rel="canonical"', false)
            ->assertSee('CollectionPage', false);

        foreach ([
            'public/search-results.blade.php',
            'public/search/results.blade.php',
            'public/availability-results.blade.php',
            'public/availability_results.blade.php',
        ] as $view) {
            $this->assertStringContainsString("@section('robots','noindex, follow", (string) file_get_contents(resource_path('views/'.$view)));
        }
    }

    public function test_responsive_image_helper_emits_modern_existing_derivatives_only(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('properties/covers/stay.jpg', 'original');
        Storage::disk('public')->put(ResponsiveImage::variantPath('properties/covers/stay.jpg', 480, 'webp'), 'webp');
        Storage::disk('public')->put(ResponsiveImage::variantPath('properties/covers/stay.jpg', 768, 'avif'), 'avif');

        $this->assertStringContainsString('480w', ResponsiveImage::srcset('properties/covers/stay.jpg', 'webp') ?? '');
        $this->assertStringContainsString('768w', ResponsiveImage::srcset('properties/covers/stay.jpg', 'avif') ?? '');
        $this->assertNull(ResponsiveImage::srcset('https://cdn.example/stay.jpg', 'webp'));
    }

    public function test_readiness_endpoint_is_sanitized_and_uses_heartbeats(): void
    {
        SystemHeartbeat::query()->create(['component' => 'scheduler', 'last_seen_at' => now()]);

        $this->get(route('health.live'))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonMissing(['database' => config('database.connections.mysql.database')]);

        $response = $this->get(route('health.ready'));
        $response->assertOk()->assertJsonPath('status', 'ready');
        $this->assertArrayNotHasKey('host', $response->json());
        $this->assertArrayNotHasKey('exception', $response->json());
    }

    public function test_release_gate_keeps_phpunit_and_playwright_out_of_github_actions(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/reserva-ci.yml'));
        $this->assertStringContainsString('azari:release-gate --ci', $workflow);
        $this->assertStringNotContainsString('phpunit', strtolower($workflow));
        $this->assertStringNotContainsString('artisan test', strtolower($workflow));
        $this->assertStringNotContainsString('playwright', strtolower($workflow));
        $this->assertFileExists(base_path('scripts/generate-responsive-image.mjs'));
        $this->assertSame([480, 768, 1200], config('reserva.media.responsive_widths'));
    }

    private function propertyWithInventory(int $inventory, string $timezone = 'Africa/Lagos'): array
    {
        $property = Property::factory()->create([
            'is_published' => true,
            'status' => 'available',
            'same_day_booking' => true,
            'timezone' => $timezone,
            'currency' => 'USD',
            'nightly_rate' => 100,
            'max_guests' => 4,
        ]);

        $type = $property->accommodationTypes()->firstOrFail();
        $type->update([
            'total_inventory' => $inventory,
            'same_day_booking' => true,
            'is_active' => true,
            'is_published' => true,
        ]);

        return [$property->fresh(), $type->fresh()];
    }
}

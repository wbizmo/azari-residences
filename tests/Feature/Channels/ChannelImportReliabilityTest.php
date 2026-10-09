<?php

namespace Tests\Feature\Channels;

use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\Property;
use App\Services\Channels\ChannelSyncService;
use App\Services\Channels\ICalChannelAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelImportReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_feed_does_not_clear_existing_external_bookings_without_explicit_review(): void
    {
        $property = Property::factory()->create();
        $connection = ChannelConnection::query()->create([
            'property_id' => $property->id,
            'provider' => 'ical',
            'name' => 'Partner calendar',
            'import_url' => 'https://example.org/calendar.ics',
            'status' => 'healthy',
            'settings' => [],
        ]);

        $reservation = ChannelReservation::query()->create([
            'channel_connection_id' => $connection->id,
            'property_id' => $property->id,
            'external_id' => 'external-reservation-001',
            'status' => 'active',
            'starts_on' => now()->addDays(7)->toDateString(),
            'ends_on' => now()->addDays(9)->toDateString(),
            'quantity' => 1,
        ]);

        try {
            app(ChannelSyncService::class)->applySnapshot($connection, []);
            $this->fail('Unexpectedly empty calendar must not release external inventory.');
        } catch (\UnexpectedValueException) {
            $this->assertSame('active', $reservation->fresh()->status);
        }

        $connection->update(['settings' => ['allow_empty_snapshot' => true]]);
        $outcome = app(ChannelSyncService::class)->applySnapshot($connection->fresh(), []);
        $this->assertSame(1, $outcome['cancelled']);
        $this->assertSame('cancelled', $reservation->fresh()->status);
    }

    public function test_ical_feed_with_unparseable_events_is_rejected_before_updating_inventory(): void
    {
        $adapter = app(ICalChannelAdapter::class);
        $this->expectException(\RuntimeException::class);

        $adapter->parse("BEGIN:VCALENDAR\nBEGIN:VEVENT\nUID:broken\nEND:VEVENT\nEND:VCALENDAR\n");
    }

    public function test_one_malformed_event_causes_whole_calendar_to_fail_closed(): void
    {
        $ical = "BEGIN:VCALENDAR\nBEGIN:VEVENT\nUID:valid\n"
            ."DTSTART;VALUE=DATE:20261101\nDTEND;VALUE=DATE:20261103\nEND:VEVENT\n"
            ."BEGIN:VEVENT\nUID:broken\nDTSTART;VALUE=DATE:invalid\n"
            ."DTEND;VALUE=DATE:20261104\nEND:VEVENT\nEND:VCALENDAR\n";

        $this->expectException(\RuntimeException::class);
        app(ICalChannelAdapter::class)->parse($ical);
    }

    public function test_missing_event_must_survive_grace_period_before_inventory_release(): void
    {
        $property = Property::factory()->create();
        $connection = ChannelConnection::query()->create([
            'property_id' => $property->id, 'provider' => 'ical',
            'name' => 'Retention calendar', 'import_url' => 'https://example.org/cal.ics',
            'status' => 'healthy', 'settings' => [],
        ]);

        foreach (['A', 'B'] as $id) {
            ChannelReservation::query()->create([
                'channel_connection_id' => $connection->id,
                'property_id' => $property->id,
                'external_id' => $id, 'status' => 'active',
                'starts_on' => '2026-11-01', 'ends_on' => '2026-11-03',
                'quantity' => 1,
            ]);
        }

        $snapshot = [[
            'external_id' => 'A', 'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-03', 'status' => 'active',
        ]];
        $sync = app(ChannelSyncService::class);
        $first = $sync->applySnapshot($connection, $snapshot);
        $this->assertSame(0, $first['cancelled']);
        $missing = ChannelReservation::query()
            ->where('channel_connection_id', $connection->id)
            ->where('external_id', 'B')->firstOrFail();
        $this->assertSame('active', $missing->status);
        $this->assertNotEmpty($missing->metadata['missing_since']);

        $this->travel(31)->minutes();
        $second = $sync->applySnapshot($connection, $snapshot);
        $this->assertSame(1, $second['cancelled']);
        $this->assertSame('cancelled', $missing->fresh()->status);
    }

    public function test_duplicate_external_uid_cannot_corrupt_snapshot_in_one_import(): void
    {
        $property = Property::factory()->create();
        $connection = ChannelConnection::query()->create([
            'property_id' => $property->id,
            'provider' => 'ical',
            'name' => 'Duplicate calendar',
            'import_url' => 'https://example.org/calendar.ics',
        ]);

        $events = [
            ['external_id' => 'X', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-03', 'status' => 'active'],
            ['external_id' => 'X', 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-04', 'status' => 'active'],
        ];

        $this->expectException(\UnexpectedValueException::class);
        try {
            app(ChannelSyncService::class)->applySnapshot($connection, $events);
        } finally {
            $this->assertSame(0, ChannelReservation::query()->where('channel_connection_id', $connection->id)->count());
        }
    }
}

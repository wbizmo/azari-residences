<?php

namespace Tests\Feature\PhaseThree;

use App\Models\AccommodationType;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Channels\ChannelAdapterManager;
use App\Services\Channels\ChannelAvailabilityService;
use App\Services\Channels\ChannelCapabilityRegistry;
use App\Services\Channels\ChannelConnectionLifecycleService;
use App\Services\Channels\ChannelSyncService;
use App\Services\Search\MarketplaceSearchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelConnectionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $hasFutureReservation = true): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $room = AccommodationType::query()->create([
            'property_id' => $property->id,
            'name' => 'Phase Three room', 'slug' => 'phase-three-room',
            'code' => 'PH3-'.$property->id, 'adult_capacity' => 2,
            'child_capacity' => 0, 'max_guests' => 2,
            'total_inventory' => 4, 'base_rate' => 10000,
            'currency' => 'NGN', 'is_active' => true, 'is_published' => true,
        ]);
        $connection = ChannelConnection::query()->create([
            'property_id' => $property->id, 'accommodation_type_id' => $room->id,
            'provider' => 'ical', 'name' => 'External feed',
            'import_url' => 'https://example.org/reservations?token=private',
            'is_active' => true, 'fail_closed' => true, 'status' => 'healthy',
            'last_successful_sync_at' => now(),
        ]);
        $reservation = null;
        if ($hasFutureReservation) {
            $start = CarbonImmutable::today()->addDays(7);
            $reservation = ChannelReservation::query()->create([
                'channel_connection_id' => $connection->id,
                'property_id' => $property->id,
                'accommodation_type_id' => $room->id,
                'external_id' => 'supplier-stay-123', 'status' => 'active',
                'starts_on' => $start->toDateString(),
                'ends_on' => $start->addDays(3)->toDateString(),
                'quantity' => 1,
            ]);
        }
        return [$owner, $property, $room, $connection, $reservation];
    }

    public function test_only_ical_is_enabled_and_unapproved_provider_capabilities_fail_closed(): void
    {
        $catalog = app(ChannelCapabilityRegistry::class);
        $this->assertTrue($catalog->supports('ical', 'reservation_import'));
        $this->assertTrue($catalog->supports('ical', 'calendar_export'));
        foreach (['booking_com', 'expedia', 'airbnb', 'pms'] as $provider) {
            $manifest = $catalog->manifest($provider);
            $this->assertFalse($manifest['enabled']);
            $this->assertTrue($manifest['requires_provider_approval']);
            $this->assertFalse($catalog->supports($provider, 'reservation_import'));
            try {
                app(ChannelAdapterManager::class)->for($provider);
                $this->fail('Unapproved provider adapter must not be returned.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('not approved', $exception->getMessage());
            }
        }
        $this->assertFalse($catalog->supports('ical', 'availability_rate_inventory_publish'));
        $this->assertFalse($catalog->supports('ical', 'webhooks'));
    }

    public function test_disconnect_preserves_reservations_and_blocks_inventory_until_verified_reconciliation(): void
    {
        [$owner, $property, $room, $connection, $reservation] = $this->fixture();
        $token = $connection->export_token;
        $start = CarbonImmutable::today()->addDays(7);
        $this->assertSame(3, app(AzariAvailabilityEngine::class)->availableQuantity($room, $start, $start->addDay()));

        $disconnected = app(ChannelConnectionLifecycleService::class)->disconnect($connection, $owner->id);
        $this->assertSame(ChannelConnectionLifecycleService::PENDING, $disconnected->status);
        $this->assertFalse($disconnected->is_active);
        $this->assertTrue($disconnected->fail_closed);
        $this->assertNull($disconnected->import_url);
        $this->assertNotSame($token, $disconnected->export_token);
        $this->assertSame('active', $reservation->fresh()->status);
        $this->assertSame(0, app(AzariAvailabilityEngine::class)->availableQuantity($room, $start, $start->addDay()));
        $this->assertSame(4, app(ChannelAvailabilityService::class)->blockedByDate($room, $start, $start->addDay())->get($start->toDateString()));
        $this->get(route('channels.export', $token))->assertNotFound();

        // Repeated requests must not rotate another token or change state.
        $this->assertSame($disconnected->export_token, app(ChannelConnectionLifecycleService::class)
            ->disconnect($connection->fresh(), $owner->id)->export_token);
    }

    public function test_disconnect_without_future_active_stays_keeps_history_without_unnecessary_block(): void
    {
        [$owner, , $room, $connection] = $this->fixture(false);
        $date = CarbonImmutable::today()->addDays(5);
        $retired = app(ChannelConnectionLifecycleService::class)->disconnect($connection, $owner->id);
        $this->assertSame(ChannelConnectionLifecycleService::DISCONNECTED, $retired->status);
        $this->assertSame(4, app(AzariAvailabilityEngine::class)->availableQuantity($room, $date, $date->addDay()));
    }

    public function test_stale_import_payload_cannot_resurrect_disconnected_connection(): void
    {
        [$owner, , , $connection, $reservation] = $this->fixture();
        app(ChannelConnectionLifecycleService::class)->disconnect($connection, $owner->id);
        $snapshot = [[
            'external_id' => 'supplier-stay-123', 'status' => 'cancelled',
            'starts_on' => $reservation->starts_on->toDateString(),
            'ends_on' => $reservation->ends_on->toDateString(),
        ]];
        $this->expectException(\UnexpectedValueException::class);
        try {
            app(ChannelSyncService::class)->applySnapshot($connection, $snapshot);
        } finally {
            $this->assertSame('active', $reservation->fresh()->status);
            $this->assertSame(ChannelConnectionLifecycleService::PENDING, $connection->fresh()->status);
        }
    }

    public function test_cross_owner_cannot_disconnect_or_sync_another_property_channel(): void
    {
        [, , , $connection] = $this->fixture();
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($stranger)
            ->delete(route('user.owner.channels.destroy', $connection))->assertNotFound();
        $this->actingAs($stranger)
            ->post(route('user.owner.channels.sync', $connection))->assertNotFound();
        $this->assertTrue($connection->fresh()->is_active);
    }

    public function test_owner_disconnection_route_retains_external_stays_and_denies_sync(): void
    {
        [$owner, , , $connection, $reservation] = $this->fixture();
        $this->actingAs($owner)->delete(route('user.owner.channels.destroy', $connection))
            ->assertRedirect();
        $this->assertSame('active', $reservation->fresh()->status);
        $this->assertSame(ChannelConnectionLifecycleService::PENDING, $connection->fresh()->status);
        $this->actingAs($owner)->post(route('user.owner.channels.sync', $connection))
            ->assertStatus(409);
    }

    public function test_disconnected_pending_stay_is_excluded_from_marketplace_and_map_results(): void
    {
        [$owner, $property, $room, $connection] = $this->fixture();
        $property->forceFill([
            'status' => 'active', 'is_published' => true,
            'latitude' => 6.5244, 'longitude' => 3.3792,
        ])->save();
        $date = CarbonImmutable::today()->addDays(7);
        $filters = [
            'check_in' => $date->toDateString(),
            'check_out' => $date->addDay()->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1,
            'property_id' => $property->id,
            'accommodation_type_id' => $room->id,
        ];
        $market = app(MarketplaceSearchService::class);
        $initial = $market->search($filters, false)['results']->getCollection();
        $this->assertCount(1, $initial);

        app(ChannelConnectionLifecycleService::class)->disconnect($connection, $owner->id);
        $after = $market->search($filters, false)['results']->getCollection();
        $this->assertCount(0, $after);
        $this->assertSame([], $market->mapCursor($filters)['points']);
    }

    public function test_admin_disable_and_owner_delete_preserve_connection_instead_of_cascading_stays(): void
    {
        [$owner, $property, , $connection, $reservation] = $this->fixture();
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true, 'email_verified_at' => now()]);
        $request = [
            'property_id' => $property->id,
            'provider' => 'ical',
            'name' => 'External feed',
            'import_url' => 'https://example.org/next.ics',
            'stale_after_minutes' => 180,
            'is_active' => '0',
        ];
        $this->actingAs($admin)->put(route('azari.admin.channels.update', $connection), $request)
            ->assertRedirect();
        $this->assertDatabaseHas('channel_connections', [
            'id' => $connection->id,
            'status' => ChannelConnectionLifecycleService::PENDING,
            'is_active' => false,
        ]);
        $this->assertSame('active', $reservation->fresh()->status);
        // No update can silently reactivate a disconnected mapping.
        $this->actingAs($admin)->put(route('azari.admin.channels.update', $connection), [
            ...$request, 'is_active' => '1',
        ])->assertStatus(409);
    }
}

<?php

namespace Tests\Feature\Architecture;

use App\Models\TravelOffer;
use App\Models\TravelRequest;
use App\Models\TravelSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AccommodationHistoricalArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function historicalRequest(User $guest): TravelRequest
    {
        $supplier = TravelSupplier::query()->create([
            'kind' => 'transfer', 'name' => 'Retired partner', 'status' => 'paused',
        ]);
        $offer = TravelOffer::query()->create([
            'travel_supplier_id' => $supplier->id,
            'kind' => 'transfer', 'title' => 'Legacy transfer enquiry',
            'timezone' => 'Africa/Lagos', 'max_party' => 1,
            'price_basis' => 'per_vehicle', 'currency' => 'NGN',
            'base_minor' => 1500000, 'tax_minor' => 0, 'fee_minor' => 0,
            'deposit_minor' => 0, 'terms' => ['included' => 'Historical only'],
            'expires_at' => now()->subDay(),
        ]);

        return TravelRequest::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $guest->id, 'travel_supplier_id' => $supplier->id,
            'travel_offer_id' => $offer->id, 'kind' => 'transfer',
            'status' => 'expired', 'idempotency_key' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', (string) Str::uuid()),
            'party_size' => 1, 'quoted_total_minor' => 1500000,
            'currency' => 'NGN', 'quote_snapshot' => ['title' => 'Legacy transfer enquiry'],
            'preferences' => ['private_note' => 'Never render my private details'],
            'expires_at' => now()->subDay(),
        ]);
    }

    public function test_guest_can_read_their_own_old_records_without_enabling_new_travel_requests(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $record = $this->historicalRequest($owner);

        $this->actingAs($owner)->get(route('user.archive.travel.index'))
            ->assertOk()->assertSee('Legacy transfer enquiry')->assertDontSee('Never render my private details')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($owner)->get(route('user.archive.travel.show', $record))
            ->assertOk()->assertSee($record->id)->assertDontSee('Never render my private details');

        $this->actingAs($owner)->postJson(route('user.travel.store'), [])->assertStatus(410);
    }

    public function test_another_guest_cannot_read_someone_elses_archive(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $record = $this->historicalRequest($owner);

        $this->actingAs($other)->get(route('user.archive.travel.index'))
            ->assertOk()->assertDontSee($record->id);
        $this->actingAs($other)->get(route('user.archive.travel.show', $record))
            ->assertNotFound();
    }

    public function test_staff_archive_is_read_only_and_protected_from_customers(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['is_admin' => true]);
        $record = $this->historicalRequest($owner);

        $this->actingAs($owner)->get(route('azari.admin.archive.travel.index'))->assertNotFound();

        $this->actingAs($admin)->get(route('azari.admin.archive.travel.index'))
            ->assertOk()->assertSee($record->id)->assertDontSee('Never render my private details');

        $this->actingAs($admin)
            ->postJson(route('azari.admin.travel.suppliers.store'), [])
            ->assertStatus(410);
    }

    public function test_historical_read_has_no_new_write_routes(): void
    {
        foreach (['user.archive.travel.index', 'user.archive.travel.show', 'azari.admin.archive.travel.index'] as $name) {
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($name);
            self::assertNotNull($route);
            self::assertSame(['GET', 'HEAD'], $route->methods());
        }
    }
}

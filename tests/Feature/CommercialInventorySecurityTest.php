<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureDojahVerified;
use App\Models\AccommodationType;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialInventorySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_property_bootstraps_default_commercial_inventory(): void
    {
        $property = Property::factory()->create([
            'is_published' => true,
            'status' => 'available',
        ]);

        $type = $property->accommodationTypes()->with('ratePlans')->first();

        $this->assertNotNull($type);
        $this->assertSame(1, (int) $type->total_inventory);
        $this->assertSame($property->currency, $type->currency);
        $this->assertTrue($type->ratePlans->contains('code', 'STANDARD'));
    }

    public function test_owner_cannot_open_another_owners_commercial_inventory(): void
    {
        $this->withoutMiddleware(EnsureDojahVerified::class);

        $ownerA = User::factory()->create(['email_verified_at' => now()]);
        $ownerB = User::factory()->create(['email_verified_at' => now()]);

        $propertyB = Property::factory()->create([
            'owner_id' => $ownerB->id,
            'ownership_type' => 'third_party',
            'managed_for_owner' => true,
        ]);

        $this->actingAs($ownerA)
            ->get(route('user.owner.commercial.edit', $propertyB))
            ->assertNotFound();
    }

    public function test_owner_cannot_update_an_accommodation_type_from_another_property(): void
    {
        $this->withoutMiddleware(EnsureDojahVerified::class);

        $ownerA = User::factory()->create(['email_verified_at' => now()]);
        $ownerB = User::factory()->create(['email_verified_at' => now()]);

        $propertyA = Property::factory()->create([
            'owner_id' => $ownerA->id,
            'ownership_type' => 'third_party',
            'managed_for_owner' => true,
        ]);

        $propertyB = Property::factory()->create([
            'owner_id' => $ownerB->id,
            'ownership_type' => 'third_party',
            'managed_for_owner' => true,
        ]);

        $foreignType = $propertyB->accommodationTypes()->firstOrFail();

        $this->actingAs($ownerA)
            ->put(
                route('user.owner.commercial.accommodations.update', [$propertyA, $foreignType]),
                $this->typePayload('Tampered Room')
            )
            ->assertNotFound();

        $this->assertNotSame('Tampered Room', $foreignType->fresh()->name);
    }

    public function test_owner_can_manage_their_own_type_and_rate_policy(): void
    {
        $this->withoutMiddleware(EnsureDojahVerified::class);

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'ownership_type' => 'third_party',
            'managed_for_owner' => true,
        ]);

        $type = $property->accommodationTypes()->firstOrFail();

        $this->actingAs($owner)
            ->put(
                route('user.owner.commercial.accommodations.update', [$property, $type]),
                $this->typePayload('Executive King', 12)
            )
            ->assertRedirect();

        $this->assertSame('Executive King', $type->fresh()->name);
        $this->assertSame(12, (int) $type->fresh()->total_inventory);

        $this->actingAs($owner)
            ->post(
                route('user.owner.commercial.rate-plans.store', [$property, $type]),
                [
                    'name' => 'Flexible breakfast',
                    'code' => 'FLEX_BREAKFAST',
                    'pricing_adjustment_type' => 'fixed',
                    'pricing_adjustment' => 25,
                    'meal_plan' => 'Breakfast included',
                    'minimum_stay' => 1,
                    'minimum_advance_days' => 0,
                    'cancellation_name' => 'Flexible 48 hours',
                    'cancellation_type' => 'flexible',
                    'free_cancel_hours' => 48,
                    'cancellation_fee_percentage' => 100,
                    'cancellation_fee_amount' => 0,
                    'no_show_policy' => 'full_stay',
                    'payment_name' => 'Deposit',
                    'payment_type' => 'deposit',
                    'deposit_type' => 'percentage',
                    'deposit_value' => 30,
                    'balance_due_days_before_arrival' => 2,
                    'is_refundable' => 1,
                    'is_active' => 1,
                    'is_public' => 1,
                ]
            )
            ->assertRedirect();

        $plan = $type->ratePlans()->where('code', 'FLEX_BREAKFAST')->firstOrFail();

        $this->assertSame('Flexible breakfast', $plan->name);
        $this->assertSame('flexible', $plan->cancellationPolicy->policy_type);
        $this->assertSame(48, (int) $plan->cancellationPolicy->free_cancel_hours);
        $this->assertSame('deposit', $plan->paymentPolicy->payment_type);
        $this->assertEqualsWithDelta(30.0, (float) $plan->paymentPolicy->deposit_value, 0.001);
    }

    private function typePayload(string $name, int $inventory = 3): array
    {
        return [
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'bedrooms' => 1,
            'bathrooms' => 1,
            'adult_capacity' => 2,
            'child_capacity' => 1,
            'max_guests' => 3,
            'total_inventory' => $inventory,
            'base_rate' => 150,
            'weekend_rate' => 175,
            'cleaning_fee' => 10,
            'service_charge' => 5,
            'security_deposit' => 50,
            'tax_rate' => 0,
            'minimum_stay' => 1,
            'is_active' => 1,
            'is_published' => 1,
        ];
    }
}

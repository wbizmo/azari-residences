<?php

namespace App\Services\Bookings;

use App\Models\AccommodationType;
use App\Models\CancellationPolicy;
use App\Models\PaymentPolicy;
use App\Models\Property;
use App\Models\RatePlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommercialInventoryManager
{
    public function saveAccommodationType(
        Property $property,
        array $data,
        ?AccommodationType $type = null
    ): AccommodationType {
        if ($type && (int) $type->property_id !== (int) $lockedProperty->getKey()) {
            abort(404);
        }

        return DB::transaction(function () use ($property, $data, $type): AccommodationType {
            $lockedProperty = Property::query()
                ->whereKey($lockedProperty->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $type = $type
                ? AccommodationType::query()->lockForUpdate()->findOrFail($type->getKey())
                : new AccommodationType(['property_id' => $lockedProperty->getKey()]);

            if ((int) ($type->property_id ?: $lockedProperty->getKey()) !== (int) $lockedProperty->getKey()) {
                abort(404);
            }

            $slug = Str::slug((string) ($data['slug'] ?? $data['name']));
            if ($slug === '') {
                throw ValidationException::withMessages(['name' => 'Enter a valid accommodation name.']);
            }

            $duplicate = AccommodationType::query()
                ->where('property_id', $lockedProperty->getKey())
                ->where('slug', $slug)
                ->when($type->exists, fn ($query) => $query->whereKeyNot($type->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => 'This property already has an accommodation type with that name/slug.',
                ]);
            }

            $payload = [
                'room_type_id' => $data['room_type_id'] ?? $lockedProperty->room_type_id,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'bedrooms' => (int) ($data['bedrooms'] ?? 1),
                'bathrooms' => (int) ($data['bathrooms'] ?? 1),
                'adult_capacity' => (int) ($data['adult_capacity'] ?? 2),
                'child_capacity' => (int) ($data['child_capacity'] ?? 0),
                'max_guests' => (int) ($data['max_guests'] ?? 2),
                'bed_configuration' => $data['bed_configuration'] ?? null,
                'room_size' => $data['room_size'] ?? null,
                'total_inventory' => (int) ($data['total_inventory'] ?? 1),
                'base_rate' => (float) ($data['base_rate'] ?? 0),
                'weekend_rate' => $data['weekend_rate'] ?? null,
                'cleaning_fee' => (float) ($data['cleaning_fee'] ?? 0),
                'service_charge' => (float) ($data['service_charge'] ?? 0),
                'security_deposit' => (float) ($data['security_deposit'] ?? 0),
                'tax_rate' => (float) ($data['tax_rate'] ?? 0),
                'currency' => strtoupper((string) ($data['currency'] ?? $lockedProperty->currency ?? 'USD')),
                'minimum_stay' => max(1, (int) ($data['minimum_stay'] ?? 1)),
                'maximum_stay' => $data['maximum_stay'] ?? null,
                'same_day_booking' => (bool) ($data['same_day_booking'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_published' => (bool) ($data['is_published'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ];

            $type->fill($payload);
            $type->property_id = $lockedProperty->getKey();

            if (! $type->code) {
                $type->code = 'RES-'.$lockedProperty->getKey().'-'.Str::upper(Str::random(6));
            }

            $type->save();

            if (! $type->ratePlans()->exists()) {
                $type->ratePlans()->create([
                    'name' => 'Standard',
                    'code' => 'STANDARD',
                    'pricing_adjustment_type' => 'none',
                    'pricing_adjustment' => 0,
                    'is_refundable' => true,
                    'is_active' => true,
                    'is_public' => true,
                    'sort_order' => 0,
                ]);
            }

            return $type->refresh();
        }, 3);
    }

    public function saveRatePlan(
        AccommodationType $type,
        array $data,
        ?RatePlan $ratePlan = null
    ): RatePlan {
        if ($ratePlan && (int) $ratePlan->accommodation_type_id !== (int) $type->getKey()) {
            abort(404);
        }

        return DB::transaction(function () use ($type, $data, $ratePlan): RatePlan {
            $lockedType = AccommodationType::query()
                ->lockForUpdate()
                ->findOrFail($type->getKey());

            $ratePlan = $ratePlan
                ? RatePlan::query()->lockForUpdate()->findOrFail($ratePlan->getKey())
                : new RatePlan(['accommodation_type_id' => $lockedType->getKey()]);

            if ((int) ($ratePlan->accommodation_type_id ?: $lockedType->getKey()) !== (int) $lockedType->getKey()) {
                abort(404);
            }

            $code = Str::upper(Str::slug((string) ($data['code'] ?? $data['name']), '_'));

            $duplicate = RatePlan::query()
                ->where('accommodation_type_id', $lockedType->getKey())
                ->where('code', $code)
                ->when($ratePlan->exists, fn ($query) => $query->whereKeyNot($ratePlan->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'code' => 'This accommodation type already has a rate plan with that code.',
                ]);
            }

            $cancellationPolicy = $this->saveCancellationPolicy(
                $lockedType->property_id,
                $ratePlan->cancellationPolicy,
                $data
            );

            $paymentPolicy = $this->savePaymentPolicy(
                $lockedType->property_id,
                $ratePlan->paymentPolicy,
                $data
            );

            $ratePlan->fill([
                'accommodation_type_id' => $lockedType->getKey(),
                'cancellation_policy_id' => $cancellationPolicy->getKey(),
                'payment_policy_id' => $paymentPolicy->getKey(),
                'name' => $data['name'],
                'code' => $code,
                'pricing_adjustment_type' => $data['pricing_adjustment_type'] ?? 'none',
                'pricing_adjustment' => (float) ($data['pricing_adjustment'] ?? 0),
                'meal_plan' => $data['meal_plan'] ?? null,
                'inclusions' => array_values(array_filter($data['inclusions'] ?? [])),
                'minimum_stay' => $data['minimum_stay'] ?? null,
                'maximum_stay' => $data['maximum_stay'] ?? null,
                'minimum_advance_days' => $data['minimum_advance_days'] ?? null,
                'maximum_advance_days' => $data['maximum_advance_days'] ?? null,
                'is_refundable' => (bool) ($data['is_refundable'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_public' => (bool) ($data['is_public'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $ratePlan->save();

            return $ratePlan->refresh()->load(['cancellationPolicy', 'paymentPolicy']);
        }, 3);
    }

    private function saveCancellationPolicy(
        int $propertyId,
        ?CancellationPolicy $policy,
        array $data
    ): CancellationPolicy {
        $policy ??= new CancellationPolicy(['property_id' => $propertyId]);

        if ($policy->exists && (int) $policy->property_id !== $propertyId) {
            abort(404);
        }

        $policy->fill([
            'property_id' => $propertyId,
            'name' => $data['cancellation_name'] ?? 'Standard cancellation',
            'policy_type' => $data['cancellation_type'] ?? 'property_default',
            'free_cancel_hours' => $data['free_cancel_hours'] ?? null,
            'fee_percentage' => (float) ($data['cancellation_fee_percentage'] ?? 0),
            'fee_amount' => (float) ($data['cancellation_fee_amount'] ?? 0),
            'charge_first_night' => (bool) ($data['charge_first_night'] ?? false),
            'no_show_policy' => $data['no_show_policy'] ?? 'same_as_cancellation',
            'is_active' => true,
        ]);
        $policy->save();

        return $policy;
    }

    private function savePaymentPolicy(
        int $propertyId,
        ?PaymentPolicy $policy,
        array $data
    ): PaymentPolicy {
        $policy ??= new PaymentPolicy(['property_id' => $propertyId]);

        if ($policy->exists && (int) $policy->property_id !== $propertyId) {
            abort(404);
        }

        $policy->fill([
            'property_id' => $propertyId,
            'name' => $data['payment_name'] ?? 'Standard payment',
            'payment_type' => $data['payment_type'] ?? 'full_prepayment',
            'deposit_type' => $data['deposit_type'] ?? null,
            'deposit_value' => $data['deposit_value'] ?? null,
            'balance_due_days_before_arrival' => $data['balance_due_days_before_arrival'] ?? null,
            'is_active' => true,
        ]);
        $policy->save();

        return $policy;
    }
}

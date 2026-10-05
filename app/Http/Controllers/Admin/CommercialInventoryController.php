<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccommodationType;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\Bookings\CommercialInventoryManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommercialInventoryController extends Controller
{
    public function edit(Property $property): View
    {
        return view('admin.properties.commercial', [
            'property' => $property->load([
                'accommodationTypes.ratePlans.cancellationPolicy',
                'accommodationTypes.ratePlans.paymentPolicy',
            ]),
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeAccommodation(
        Request $request,
        Property $property,
        CommercialInventoryManager $manager
    ): RedirectResponse {
        $manager->saveAccommodationType($property, $this->accommodationData($request));

        return back()->with('status', 'Accommodation type created.');
    }

    public function updateAccommodation(
        Request $request,
        Property $property,
        AccommodationType $accommodationType,
        CommercialInventoryManager $manager
    ): RedirectResponse {
        $this->assertTypeBelongsToProperty($property, $accommodationType);
        $manager->saveAccommodationType($property, $this->accommodationData($request), $accommodationType);

        return back()->with('status', 'Accommodation type updated.');
    }

    public function storeRatePlan(
        Request $request,
        Property $property,
        AccommodationType $accommodationType,
        CommercialInventoryManager $manager
    ): RedirectResponse {
        $this->assertTypeBelongsToProperty($property, $accommodationType);
        $manager->saveRatePlan($accommodationType, $this->ratePlanData($request));

        return back()->with('status', 'Rate plan created.');
    }

    public function updateRatePlan(
        Request $request,
        Property $property,
        AccommodationType $accommodationType,
        RatePlan $ratePlan,
        CommercialInventoryManager $manager
    ): RedirectResponse {
        $this->assertTypeBelongsToProperty($property, $accommodationType);
        abort_unless((int) $ratePlan->accommodation_type_id === (int) $accommodationType->getKey(), 404);

        $manager->saveRatePlan($accommodationType, $this->ratePlanData($request), $ratePlan);

        return back()->with('status', 'Rate plan updated.');
    }

    private function assertTypeBelongsToProperty(Property $property, AccommodationType $type): void
    {
        abort_unless((int) $type->property_id === (int) $property->getKey(), 404);
    }

    private function accommodationData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'description' => ['nullable', 'string', 'max:3000'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'adult_capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'child_capacity' => ['required', 'integer', 'min:0', 'max:100'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:100'],
            'bed_configuration' => ['nullable', 'string', 'max:255'],
            'room_size' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'total_inventory' => ['required', 'integer', 'min:1', 'max:10000'],
            'base_rate' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'weekend_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'cleaning_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'service_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'security_deposit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $data['same_day_booking'] = $request->boolean('same_day_booking');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }

    private function ratePlanData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80'],
            'pricing_adjustment_type' => ['required', Rule::in(['none', 'fixed', 'percentage'])],
            'pricing_adjustment' => ['nullable', 'numeric', 'between:-1000000000,1000000000'],
            'meal_plan' => ['nullable', 'string', 'max:120'],
            'inclusions' => ['nullable', 'array', 'max:20'],
            'inclusions.*' => ['nullable', 'string', 'max:160'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'minimum_advance_days' => ['nullable', 'integer', 'min:0', 'max:1095'],
            'maximum_advance_days' => ['nullable', 'integer', 'min:0', 'max:1095'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'cancellation_name' => ['nullable', 'string', 'max:180'],
            'cancellation_type' => ['nullable', Rule::in(['property_default', 'flexible', 'non_refundable'])],
            'free_cancel_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'cancellation_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cancellation_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'no_show_policy' => ['nullable', Rule::in(['same_as_cancellation', 'full_stay', 'first_night'])],
            'payment_name' => ['nullable', 'string', 'max:180'],
            'payment_type' => ['required', Rule::in(['full_prepayment', 'deposit', 'pay_later', 'pay_at_property'])],
            'deposit_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'deposit_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'balance_due_days_before_arrival' => ['nullable', 'integer', 'min:0', 'max:1095'],
        ]);

        if (($data['minimum_stay'] ?? null) && ($data['maximum_stay'] ?? null)
            && (int) $data['maximum_stay'] < (int) $data['minimum_stay']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'maximum_stay' => 'Maximum stay cannot be less than minimum stay.',
            ]);
        }

        if (($data['minimum_advance_days'] ?? null) !== null
            && ($data['maximum_advance_days'] ?? null) !== null
            && (int) $data['maximum_advance_days'] < (int) $data['minimum_advance_days']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'maximum_advance_days' => 'Maximum advance days cannot be less than minimum advance days.',
            ]);
        }

        if (($data['payment_type'] ?? null) === 'deposit' && empty($data['deposit_type'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deposit_type' => 'Choose how the deposit is calculated.',
            ]);
        }

        if (($data['payment_type'] ?? null) === 'deposit' && ! isset($data['deposit_value'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deposit_value' => 'Enter the deposit amount or percentage.',
            ]);
        }

        if (($data['deposit_type'] ?? null) === 'percentage' && (float) ($data['deposit_value'] ?? 0) > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deposit_value' => 'Percentage deposits cannot exceed 100%.',
            ]);
        }

        $data['is_refundable'] = $request->boolean('is_refundable');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_public'] = $request->boolean('is_public');
        $data['charge_first_night'] = $request->boolean('charge_first_night');

        return $data;
    }
}

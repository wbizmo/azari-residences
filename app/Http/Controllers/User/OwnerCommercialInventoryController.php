<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommercialInventory\AccommodationTypeRequest;
use App\Http\Requests\CommercialInventory\RatePlanRequest;
use App\Models\AccommodationType;
use App\Models\Property;
use App\Models\InventoryChangeLog;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\Bookings\CommercialInventoryManager;
use App\Services\Bookings\InventoryBulkUpdateService;
use App\Services\Owners\PropertyAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerCommercialInventoryController extends Controller
{
    public function edit(Request $request, Property $property): View
    {
        $this->authorizeOwner($request, $property);

        return view('user.owner.commercial', [
            'property' => $property->load([
                'accommodationTypes.ratePlans.cancellationPolicy',
                'accommodationTypes.ratePlans.paymentPolicy',
            ]),
            'roomTypes' => RoomType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeAccommodation(
        AccommodationTypeRequest $request,
        Property $property,
        CommercialInventoryManager $manager
    ): RedirectResponse {
        $this->authorizeOwner($request, $property);
        $manager->saveAccommodationType($property, $request->validated());

        return back()->with('status', 'Accommodation type created.');
    }

    public function updateAccommodation(
        AccommodationTypeRequest $request,
        Property $property,
        AccommodationType $accommodationType,
        CommercialInventoryManager $manager
    ): RedirectResponse {
        $this->authorizeOwner($request, $property);
        $this->assertTypeBelongsToProperty($property, $accommodationType);
        $manager->saveAccommodationType($property, $request->validated(), $accommodationType);

        return back()->with('status', 'Accommodation type updated.');
    }

    public function storeRatePlan(
        RatePlanRequest $request,
        Property $property,
        AccommodationType $accommodationType,
        CommercialInventoryManager $manager
    ): RedirectResponse {
        $this->authorizeOwner($request, $property);
        $this->assertTypeBelongsToProperty($property, $accommodationType);
        $manager->saveRatePlan($accommodationType, $request->validated());

        return back()->with('status', 'Rate plan created.');
    }

    public function updateRatePlan(
        RatePlanRequest $request,
        Property $property,
        AccommodationType $accommodationType,
        RatePlan $ratePlan,
        CommercialInventoryManager $manager
    ): RedirectResponse {
        $this->authorizeOwner($request, $property);
        $this->assertTypeBelongsToProperty($property, $accommodationType);
        abort_unless((int) $ratePlan->accommodation_type_id === (int) $accommodationType->getKey(), 404);

        $manager->saveRatePlan($accommodationType, $request->validated(), $ratePlan);

        return back()->with('status', 'Rate plan updated.');
    }

    public function bulkUpdate(
        Request $request,
        Property $property,
        AccommodationType $accommodationType,
        InventoryBulkUpdateService $inventory
    ): RedirectResponse {
        $this->authorizeOwner($request, $property);
        $this->assertTypeBelongsToProperty($property, $accommodationType);

        $data = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'sellable_inventory' => ['nullable', 'integer', 'min:0'],
            'maintenance_inventory' => ['nullable', 'integer', 'min:0'],
            'stop_sell' => ['nullable', 'boolean'],
            'closed_to_arrival' => ['nullable', 'boolean'],
            'closed_to_departure' => ['nullable', 'boolean'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'expected_revision' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        $changes = collect($data)->only([
            'sellable_inventory',
            'maintenance_inventory',
            'minimum_stay',
            'maximum_stay',
            'price_override',
        ])->filter(fn ($value) => $value !== null && $value !== '')->all();

        foreach (['stop_sell', 'closed_to_arrival', 'closed_to_departure'] as $boolean) {
            if ($request->has($boolean)) {
                $changes[$boolean] = $request->boolean($boolean);
            }
        }

        $inventory->apply(
            $accommodationType,
            CarbonImmutable::parse($data['from_date']),
            CarbonImmutable::parse($data['to_date']),
            $changes,
            $request->user()->getKey(),
            'owner',
            $data['expected_revision']
        );

        return back()->with('status', 'Inventory calendar updated and audited.');
    }


    public function previewBulkUpdate(
        Request $request,
        Property $property,
        AccommodationType $accommodationType,
        InventoryBulkUpdateService $inventory
    ): JsonResponse {
        $this->authorizeOwner($request, $property);
        $this->assertTypeBelongsToProperty($property, $accommodationType);

        $data = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'sellable_inventory' => ['nullable', 'integer', 'min:0'],
            'maintenance_inventory' => ['nullable', 'integer', 'min:0'],
            'stop_sell' => ['nullable', 'boolean'],
            'closed_to_arrival' => ['nullable', 'boolean'],
            'closed_to_departure' => ['nullable', 'boolean'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $changes = collect($data)->only([
            'sellable_inventory', 'maintenance_inventory', 'minimum_stay',
            'maximum_stay', 'price_override',
        ])->filter(fn ($value) => $value !== null && $value !== '')->all();

        foreach (['stop_sell', 'closed_to_arrival', 'closed_to_departure'] as $boolean) {
            if ($request->has($boolean)) {
                $changes[$boolean] = $request->boolean($boolean);
            }
        }

        return response()->json($inventory->preview(
            $accommodationType,
            CarbonImmutable::parse($data['from_date']),
            CarbonImmutable::parse($data['to_date']),
            $changes
        ));
    }

    public function undoBulkUpdate(
        Request $request,
        Property $property,
        InventoryChangeLog $log,
        InventoryBulkUpdateService $inventory
    ): RedirectResponse {
        $this->authorizeOwner($request, $property);
        abort_unless((int) $log->property_id === (int) $property->id, 404);

        $inventory->undo($log, $request->user()->id);

        return back()->with('status', 'Calendar change safely undone.');
    }

    private function authorizeOwner(Request $request, Property $property): void
    {
        abort_unless($property->managed_for_owner, 404);
        app(PropertyAccessService::class)->assert($request->user(), $property, 'inventory.manage');
    }

    private function assertTypeBelongsToProperty(Property $property, AccommodationType $type): void
    {
        abort_unless((int) $type->property_id === (int) $property->getKey(), 404);
    }
}

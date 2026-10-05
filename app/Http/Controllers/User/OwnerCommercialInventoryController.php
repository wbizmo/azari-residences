<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommercialInventory\AccommodationTypeRequest;
use App\Http\Requests\CommercialInventory\RatePlanRequest;
use App\Models\AccommodationType;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\Bookings\CommercialInventoryManager;
use Illuminate\Http\RedirectResponse;
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

    private function authorizeOwner(Request $request, Property $property): void
    {
        abort_unless(
            $request->user()
            && (int) $property->owner_id === (int) $request->user()->getKey()
            && $property->managed_for_owner,
            404
        );
    }

    private function assertTypeBelongsToProperty(Property $property, AccommodationType $type): void
    {
        abort_unless((int) $type->property_id === (int) $property->getKey(), 404);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommercialInventory\AccommodationTypeRequest;
use App\Http\Requests\CommercialInventory\RatePlanRequest;
use App\Models\AccommodationType;
use App\Models\InventoryChangeLog;
use App\Services\Bookings\InventoryBulkUpdateService;
use App\Services\Bookings\OwnerInventoryCalendarService;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\Bookings\CommercialInventoryManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CommercialInventoryController extends Controller
{
    public function edit(Property $property, OwnerInventoryCalendarService $calendar): View
    {
        $view = request()->query('calendar_view') === 'month' ? 'month' : 'week';
        $date = CarbonImmutable::today(LocalDate::propertyTimezone($property));
        if (request()->filled('calendar_date')) {
            $value = (string) request()->query('calendar_date');
            if (! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
                abort(422, 'Invalid calendar date.');
            }
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, LocalDate::propertyTimezone($property));
            if (! $date || $date->toDateString() !== $value) {
                abort(422, 'Invalid calendar date.');
            }
        }

        return view('admin.properties.commercial', [
            'inventoryBoard' => $calendar->forProperty($property, $date, $view),
            'calendarLogs' => $property->inventoryChangeLogs()->latest('id')->limit(20)->get(),
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
        $manager->saveAccommodationType($property, $request->validated());

        return back()->with('status', 'Accommodation type created.');
    }

    public function updateAccommodation(
        AccommodationTypeRequest $request,
        Property $property,
        AccommodationType $accommodationType,
        CommercialInventoryManager $manager
    ): RedirectResponse {
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
        $this->assertTypeBelongsToProperty($property, $accommodationType);
        abort_unless((int) $ratePlan->accommodation_type_id === (int) $accommodationType->getKey(), 404);

        $manager->saveRatePlan($accommodationType, $request->validated(), $ratePlan);

        return back()->with('status', 'Rate plan updated.');
    }

    public function previewCalendar(Request $request, Property $property,
        AccommodationType $accommodationType, InventoryBulkUpdateService $inventory): JsonResponse
    {
        $this->assertTypeBelongsToProperty($property, $accommodationType);
        [$data, $changes] = $this->calendarData($request);

        return response()->json($inventory->preview(
            $accommodationType,
            CarbonImmutable::parse($data['from_date']),
            CarbonImmutable::parse($data['to_date']),
            $changes
        ));
    }

    public function applyCalendar(Request $request, Property $property,
        AccommodationType $accommodationType, InventoryBulkUpdateService $inventory): RedirectResponse
    {
        $this->assertTypeBelongsToProperty($property, $accommodationType);
        [$data, $changes] = $this->calendarData($request, true);
        $inventory->apply(
            $accommodationType,
            CarbonImmutable::parse($data['from_date']),
            CarbonImmutable::parse($data['to_date']),
            $changes,
            $request->user()?->getKey(),
            'admin',
            $data['expected_revision']
        );

        return back()->with('status', 'Reviewed calendar changes saved and audited.');
    }

    public function undoCalendar(Request $request, Property $property,
        InventoryChangeLog $log, InventoryBulkUpdateService $inventory): RedirectResponse
    {
        abort_unless((int) $log->property_id === (int) $property->getKey(), 404);
        $inventory->undo($log, $request->user()?->getKey());

        return back()->with('status', 'Calendar update safely undone.');
    }

    /** @return array{array, array} */
    private function calendarData(Request $request, bool $apply = false): array
    {
        $data = $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'sellable_inventory' => ['nullable', 'integer', 'min:0'],
            'maintenance_inventory' => ['nullable', 'integer', 'min:0'],
            'stop_sell' => ['nullable', 'boolean'],
            'closed_to_arrival' => ['nullable', 'boolean'],
            'closed_to_departure' => ['nullable', 'boolean'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'expected_revision' => [$apply ? 'required' : 'nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $changes = collect($data)->only([
            'sellable_inventory', 'maintenance_inventory', 'minimum_stay',
            'maximum_stay', 'price_override',
        ])->filter(fn ($value) => $value !== null && $value !== '')->all();
        foreach (['stop_sell', 'closed_to_arrival', 'closed_to_departure'] as $field) {
            if ($request->filled($field)) {
                $changes[$field] = $request->boolean($field);
            }
        }

        return [$data, $changes];
    }

    private function assertTypeBelongsToProperty(Property $property, AccommodationType $type): void
    {
        abort_unless((int) $type->property_id === (int) $property->getKey(), 404);
    }
}

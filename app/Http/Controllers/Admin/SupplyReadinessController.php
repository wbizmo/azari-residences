<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Services\Owners\SupplyReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SupplyReadinessController extends Controller
{
    public function __invoke(Request $request, SupplyReadinessService $service): View
    {
        $data = $request->validate([
            'check_in' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date_format:Y-m-d', 'after:check_in'],
        ]);
        $checkIn = CarbonImmutable::parse($data['check_in'] ?? now()->addDays(2)->toDateString());
        $checkOut = CarbonImmutable::parse($data['check_out'] ?? $checkIn->addDay()->toDateString());
        abort_if($checkIn->diffInDays($checkOut) > 30, 422, 'Supply inspection is limited to 30 nights.');

        // Aggregated stage counts reflect real records, not fabricated stock.
        $counts = [
            'owner_drafts' => PropertyListing::query()->where('status', 'draft')->count(),
            'owner_submitted' => PropertyListing::query()->whereIn('status', ['submitted', 'under_review', 'correction_requested'])->count(),
            'published' => Property::query()->where('is_published', true)->count(),
            'unpublished' => Property::query()->where('is_published', false)->count(),
        ];

        $properties = Property::query()
            ->with(['locationRecord', 'owner', 'photoModerations', 'amenities',
                'ownerAgreement', 'ownerListing', 'accommodationTypes.ratePlans.paymentPolicy',
                'accommodationTypes.ratePlans.cancellationPolicy'])
            ->orderBy('id')
            ->paginate(20)->withQueryString();

        $inspections = [];
        foreach ($properties as $property) {
            $inspections[$property->id] = $service->inspect($property, $checkIn, $checkOut);
        }

        return view('admin.properties.supply-readiness', compact(
            'properties', 'inspections', 'counts', 'checkIn', 'checkOut'
        ));
    }
}

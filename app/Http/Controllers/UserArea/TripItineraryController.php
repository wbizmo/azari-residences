<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\TripItinerary;
use App\Models\TravelRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TripItineraryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $itinerary = TripItinerary::query()->create([
            'user_id' => $request->user()->id,
            'name' => trim($data['name']),
        ]);

        AuditLog::record('trip_itinerary.created', $itinerary, [], [
            'itinerary_id' => $itinerary->id,
        ]);

        return redirect()->route('user.itineraries.show', $itinerary)
            ->with('success', 'Itinerary created. Add stays from each booking page.');
    }

    public function show(Request $request, TripItinerary $itinerary): View
    {
        abort_unless((int) $itinerary->user_id === (int) $request->user()->id, 404);

        $bookings = $itinerary->bookings()
            ->where('user_id', $request->user()->id)
            ->with(['property', 'accommodationType', 'ratePlan'])
            ->orderBy('check_in')
            ->orderBy('id')
            ->paginate(20);

        $travelRequests = TravelRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('trip_itinerary_id', $itinerary->id)
            ->with('offer:id,title')
            ->latest()->paginate(10, ['*'], 'travel_page');

        return view('user.bookings.itinerary', compact('itinerary', 'bookings', 'travelRequests'));
    }

    public function assign(Request $request, string $reference): RedirectResponse
    {
        $data = $request->validate([
            'trip_itinerary_id' => [
                'nullable', 'integer',
                Rule::exists('trip_itineraries', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $target = isset($data['trip_itinerary_id'])
            ? (int) $data['trip_itinerary_id']
            : null;

        DB::transaction(function () use ($request, $reference, $target): void {
            $booking = Booking::query()
                ->where('reference', $reference)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($target !== null) {
                // Revalidate under the transaction in case another account
                // deleted or transferred the trip between form and submit.
                abort_unless(TripItinerary::query()
                    ->whereKey($target)
                    ->where('user_id', $request->user()->id)
                    ->exists(), 404);
            }

            $previous = $booking->trip_itinerary_id;
            if ((int) $previous === (int) $target) {
                return;
            }
            $booking->trip_itinerary_id = $target;
            $booking->save();

            AuditLog::record('trip_itinerary.booking_assigned', $booking,
                ['trip_itinerary_id' => $previous],
                ['trip_itinerary_id' => $target]);
        }, 3);

        return back()->with('success', $target === null
            ? 'Stay removed from the itinerary.'
            : 'Stay added to the itinerary.');
    }

    public function destroy(Request $request, TripItinerary $itinerary): RedirectResponse
    {
        abort_unless((int) $itinerary->user_id === (int) $request->user()->id, 404);

        DB::transaction(function () use ($request, $itinerary): void {
            $locked = TripItinerary::query()->whereKey($itinerary->id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Deleting a grouping never deletes, cancels or edits a booking.
            $locked->bookings()->where('user_id', $request->user()->id)
                ->update(['trip_itinerary_id' => null]);

            AuditLog::record('trip_itinerary.deleted', $locked, [
                'itinerary_id' => $locked->id,
            ]);
            $locked->delete();
        }, 3);

        return redirect()->route('user.bookings.index', ['status' => 'all'])
            ->with('success', 'Itinerary removed. Your individual bookings remain unchanged.');
    }
}

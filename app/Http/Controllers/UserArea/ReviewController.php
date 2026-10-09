<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403);
        abort_unless(
            in_array($booking->status, ['completed', 'checked_out'], true)
            || filled($booking->checked_out_at)
            || filled($booking->completed_at),
            422
        );
        abort_if(Review::query()->where('booking_id', $booking->id)->exists(), 422);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'cleanliness' => ['required', 'integer', 'min:1', 'max:5'],
            'comfort' => ['required', 'integer', 'min:1', 'max:5'],
            'facilities' => ['required', 'integer', 'min:1', 'max:5'],
            'location_score' => ['required', 'integer', 'min:1', 'max:5'],
            'staff_service' => ['required', 'integer', 'min:1', 'max:5'],
            'value_score' => ['required', 'integer', 'min:1', 'max:5'],
            'wifi_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],
            'positive_feedback' => ['nullable', 'string', 'max:1500'],
            'negative_feedback' => ['nullable', 'string', 'max:1500'],
            'trip_type' => ['nullable', Rule::in(['business', 'couple', 'family', 'friends', 'solo', 'other'])],
        ]);

        $review = Review::query()->create([
            ...$data,
            'booking_id' => $booking->id,
            'property_id' => $booking->property_id,
            'user_id' => $request->user()->id,
            'verified_stay' => true,
            'status' => 'pending',
        ]);

        AuditLog::record('review.submitted', $review, [], [
            'booking_reference' => $booking->reference,
            'property_id' => $booking->property_id,
            'verified_stay' => true,
        ]);

        return back()->with('success', 'Your verified-stay review was submitted for moderation.');
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403);

        $review = Review::query()
            ->where('booking_id', $booking->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'cleanliness' => ['required', 'integer', 'min:1', 'max:5'],
            'comfort' => ['required', 'integer', 'min:1', 'max:5'],
            'facilities' => ['required', 'integer', 'min:1', 'max:5'],
            'location_score' => ['required', 'integer', 'min:1', 'max:5'],
            'staff_service' => ['required', 'integer', 'min:1', 'max:5'],
            'value_score' => ['required', 'integer', 'min:1', 'max:5'],
            'wifi_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],
            'positive_feedback' => ['nullable', 'string', 'max:1500'],
            'negative_feedback' => ['nullable', 'string', 'max:1500'],
            'trip_type' => ['nullable', Rule::in(['business', 'couple', 'family', 'friends', 'solo', 'other'])],
        ]);

        $before = $review->only(array_keys($data));
        $review->update([
            ...$data,
            'edited_at' => now(),
            'status' => 'pending',
        ]);

        AuditLog::record('review.updated', $review, $before, [
            ...$review->only(array_keys($data)),
            'edited_at' => $review->edited_at?->toIso8601String(),
        ]);

        return back()->with('success', 'Your review was updated and returned to moderation.');
    }

}

<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Review;
use App\Models\ReviewAppeal;
use App\Services\Reviews\ReviewPublicationGuard;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
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
            'language' => ['nullable', Rule::in(['und','en','fr','es','de','pt','ar','hi','zh','it','yo','ig','ha','other'])],
        ]);

        $data['language'] = $data['language'] ?? 'und';
        $this->assertNoContactDetails($data);

        // Serialize attempts for one booking before checking eligibility.
        // The database unique constraint is the final defence for retries.
        $review = DB::transaction(function () use ($booking, $request, $data): Review {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === (int) $request->user()->id, 403);
            abort_unless(
                in_array($locked->status, ['completed', 'checked_out'], true)
                || filled($locked->checked_out_at)
                || filled($locked->completed_at),
                422
            );
            abort_if(Review::query()->where('booking_id', $locked->id)->exists(), 422);

            return Review::query()->create([
                ...$data,
                'booking_id' => $locked->id,
                'property_id' => $locked->property_id,
                'user_id' => $request->user()->id,
                'verified_stay' => true,
                'status' => 'pending',
            ]);
        }, 3);

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
            'language' => ['nullable', Rule::in(['und','en','fr','es','de','pt','ar','hi','zh','it','yo','ig','ha','other'])],
        ]);

        $data['language'] = $data['language'] ?? 'und';
        $this->assertNoContactDetails($data);

        DB::transaction(function () use ($review, $data): void {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(array_keys($data));
            $locked->update([
                ...$data,
                'edited_at' => now(),
                'status' => 'pending',
                // A property response to the old guest text must be moderated
                // again after review content changes.
                'owner_reply_status' => 'pending',
            ]);

            // Helpfulness belongs to the text readers evaluated. A rewritten
            // review starts with a clean tally when it is reapproved.
            $locked->helpfulVotes()->delete();

            AuditLog::record('review.updated', $locked, $before, [
                ...$locked->only(array_keys($data)),
                'edited_at' => $locked->edited_at?->toIso8601String(),
            ]);
        }, 3);

        return back()->with('success', 'Your review was updated and returned to moderation.');
    }

    public function appeal(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $booking, $data): void {
            $review = Review::query()
                ->where('booking_id', $booking->id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(in_array($review->status, ['hidden', 'flagged', 'archived'], true), 422);
            abort_if(ReviewAppeal::query()->where('review_id', $review->id)->exists(), 422,
                'A moderation appeal has already been submitted for this review.');

            $appeal = ReviewAppeal::query()->create([
                'review_id' => $review->id,
                'user_id' => $request->user()->id,
                'reason' => $data['reason'],
                'status' => 'pending',
            ]);

            AuditLog::record('review.appeal_submitted', $review, [], [
                'appeal_id' => $appeal->id,
                'booking_id' => $booking->id,
            ]);
        }, 3);

        return back()->with('success', 'Your review moderation appeal was submitted.');
    }

    private function assertNoContactDetails(array $fields): void
    {
        if (app(ReviewPublicationGuard::class)->containsContactDetails($fields)) {
            throw ValidationException::withMessages([
                'body' => 'For privacy, remove email addresses, phone numbers and website links before submitting a public review.',
            ]);
        }
    }

}

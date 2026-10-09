<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PropertyStaffMembership;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewHelpfulController extends Controller
{
    public function store(Request $request, Review $review): RedirectResponse
    {
        $review->loadMissing('property');
        $property = $review->property;
        abort_unless(
            $review->verified_stay && $review->status === 'approved'
            && $property?->is_published
            && ! in_array($property->status, ['inactive','unavailable','maintenance','archived'], true),
            404
        );

        $user = $request->user();
        abort_unless((int) $review->user_id !== (int) $user->id, 403,
            'You cannot vote for your own review.');
        abort_unless((int) $property->owner_id !== (int) $user->id, 403,
            'Property staff cannot vote for their own listing.');
        abort_if(PropertyStaffMembership::query()
            ->where('property_id', $property->id)
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->whereNull('revoked_at')->exists(), 403,
            'Property staff cannot vote for their own listing.');

        DB::transaction(function () use ($review, $user): void {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'approved' && $locked->verified_stay, 404);
            // SQL-level uniqueness + insertOrIgnore makes browser retries and
            // concurrent tabs idempotent without inflating review scores.
            $inserted = DB::table('review_helpful_votes')->insertOrIgnore([
                'review_id' => $review->id,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted) {
                AuditLog::record('review.helpful_voted', $review, [], [
                    'voter_id' => $user->id,
                ]);
            }
        }, 3);

        return back()->with('success', 'Your helpful vote was recorded.');
    }
}

<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use App\Models\ReviewReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewInteractionController extends Controller
{
    public function helpful(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->verified_stay && $review->status === 'approved', 404);
        abort_if((int) $review->user_id === (int) $request->user()->id, 422, 'You cannot rate your own review.');

        $vote = ReviewHelpfulVote::query()
            ->where('review_id', $review->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($vote) {
            $vote->delete();
            return back()->with('success', 'Helpful vote removed.');
        }

        ReviewHelpfulVote::query()->create([
            'review_id' => $review->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Marked as helpful.');
    }

    public function report(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->verified_stay && $review->status === 'approved', 404);
        abort_if((int) $review->user_id === (int) $request->user()->id, 422, 'Use review editing if you need to change your own review.');

        $data = $request->validate([
            'reason' => ['required', Rule::in(['abusive', 'pii', 'retaliatory', 'fake', 'other'])],
            'details' => ['nullable', 'string', 'max:1500'],
        ]);

        $report = ReviewReport::query()->updateOrCreate(
            ['review_id' => $review->id, 'user_id' => $request->user()->id],
            [
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        AuditLog::record('review.reported', $review, [], [
            'report_id' => $report->id,
            'reason' => $report->reason,
        ]);

        return back()->with('success', 'Review report submitted for moderation.');
    }
}

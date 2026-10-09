<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;
use App\Models\ReviewAppeal;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $query = Review::query()
            ->with(['user', 'booking.property', 'property', 'appeal'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->boolean('verified_only')) {
            $query->where('verified_stay', true);
        }

        return view('admin.reviews.index', [
            'reviews' => $query->paginate(10)->withQueryString(),
        ]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'hidden', 'archived', 'flagged'])],
            'featured' => ['nullable', 'boolean'],
            'admin_reply' => ['nullable', 'string', 'max:2000'],
            'owner_reply_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'moderation_reason' => [
                Rule::requiredIf(in_array($request->input('status'), ['hidden', 'flagged', 'archived'], true)),
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $old = $review->toArray();
        $replyChanged = (string) ($review->admin_reply ?? '') !== (string) ($data['admin_reply'] ?? '');
        $wasHidden = in_array($review->status, ['hidden', 'flagged', 'archived'], true);
        $willBeHidden = in_array($data['status'], ['hidden', 'flagged', 'archived'], true);

        $review->update([
            'status' => $data['status'],
            'featured' => $request->boolean('featured'),
            'owner_reply_status' => filled($review->owner_reply)
                ? ($data['owner_reply_status'] ?? $review->owner_reply_status ?? 'pending')
                : 'pending',
            'admin_reply' => $data['admin_reply'] ?? null,
            'management_reply_by' => $replyChanged && filled($data['admin_reply'] ?? null)
                ? $request->user()->id
                : $review->management_reply_by,
            'management_replied_at' => $replyChanged && filled($data['admin_reply'] ?? null)
                ? now()
                : $review->management_replied_at,
            'moderation_reason' => $data['moderation_reason'] ?? null,
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
            'hidden_at' => $willBeHidden ? ($review->hidden_at ?: now()) : null,
            'restored_at' => $wasHidden && ! $willBeHidden ? now() : $review->restored_at,
        ]);

        AuditLog::record(
            'review.moderated',
            $review,
            $old,
            $review->toArray(),
            ['guest_text_modified' => false]
        );

        return back()->with('success', 'Review moderation updated.');
    }
    public function decideAppeal(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'decision_note' => [
                Rule::requiredIf($request->input('decision') === 'rejected'),
                'nullable', 'string', 'min:10', 'max:2000',
            ],
        ]);

        DB::transaction(function () use ($request, $review, $data): void {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $appeal = ReviewAppeal::query()
                ->where('review_id', $locked->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($appeal->status === 'pending', 422, 'This appeal has already been decided.');

            if ($data['decision'] === 'accepted') {
                abort_unless(in_array($locked->status, ['hidden', 'flagged', 'archived'], true),
                    422, 'This review is no longer hidden or flagged.');
                $before = $locked->only(['status', 'hidden_at', 'restored_at']);
                $locked->update([
                    'status' => 'approved',
                    'hidden_at' => null,
                    'restored_at' => now(),
                    'moderated_by' => $request->user()->id,
                    'moderated_at' => now(),
                ]);
                AuditLog::record('review.appeal_review_restored', $locked, $before,
                    $locked->only(array_keys($before)), ['appeal_id' => $appeal->id]);
            }

            $appeal->update([
                'status' => $data['decision'],
                'decision_note' => $data['decision_note'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            AuditLog::record('review.appeal_decided', $locked, [], [
                'appeal_id' => $appeal->id,
                'decision' => $data['decision'],
                'actor_id' => $request->user()->id,
            ]);
        }, 3);

        return back()->with('success', 'Review appeal decision recorded.');
    }

}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $query = Review::query()
            ->with(['user', 'booking.property', 'property', 'reports' => fn ($query) => $query->latest()])
            ->withCount(['helpfulVotes', 'reports', 'reports as pending_reports_count' => fn ($query) => $query->where('status', 'pending')])
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

        $review->reports()->where('status', 'pending')->update([
            'status' => $willBeHidden ? 'actioned' : 'reviewed',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'updated_at' => now(),
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
}

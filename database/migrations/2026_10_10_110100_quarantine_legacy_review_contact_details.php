<?php

use App\Models\AuditLog;
use App\Models\Review;
use App\Services\Reviews\ReviewPublicationGuard;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $guard = app(ReviewPublicationGuard::class);

        // Scan only previously published reviews. Approved reviews with phone
        // numbers, links or email addresses must not remain public by default.
        Review::query()->where('status', 'approved')
            ->select(['id', 'title', 'body', 'positive_feedback', 'negative_feedback'])
            ->chunkById(100, function ($reviews) use ($guard): void {
                foreach ($reviews as $review) {
                    if (! $guard->containsContactDetails($review->only([
                        'title', 'body', 'positive_feedback', 'negative_feedback',
                    ]))) {
                        continue;
                    }

                    $changed = Review::query()->whereKey($review->id)
                        ->where('status', 'approved')
                        ->update([
                            'status' => 'flagged',
                            'moderation_reason' => 'Review contains contact details; guest edit is required before public restoration.',
                            'hidden_at' => now(),
                            'updated_at' => now(),
                        ]);

                    if ($changed === 1) {
                        AuditLog::record('review.legacy_privacy_quarantined', $review,
                            ['status' => 'approved'], ['status' => 'flagged']);
                    }
                }
            });
    }

    // Deliberately never re-publish private contact data on rollback.
    public function down(): void {}
};

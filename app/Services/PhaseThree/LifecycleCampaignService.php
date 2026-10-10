<?php

namespace App\Services\PhaseThree;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Consent is checked at reservation AND again by the sender, never inferred from transactional preference. */
final class LifecycleCampaignService
{
    public function eligible(User $user): bool
    {
        if (! $user->email_verified_at || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)
            || ! (bool) $user->marketing_consent) {
            return false;
        }
        $preference = $user->communicationPreference()->first();
        return (bool) ($preference?->email_marketing ?? false);
    }

    public function reserve(User $user, int $campaignId, string $eventKey): bool
    {
        if (! preg_match('/^[a-zA-Z0-9:._-]{8,100}$/', $eventKey)) {
            throw new \InvalidArgumentException('Invalid marketing event key.');
        }
        return DB::transaction(function () use ($user, $campaignId, $eventKey): bool {
            $campaign = DB::table('marketing_campaigns')->where('id', $campaignId)->lockForUpdate()->first();
            if (! $campaign || $campaign->status !== 'approved' || ! $campaign->approved_at
                || ($campaign->starts_at && $campaign->starts_at > now())
                || ($campaign->ends_at && $campaign->ends_at <= now())
                || ! $this->eligible($user)) {
                return false;
            }
            $previous = DB::table('marketing_deliveries')->where('user_id', $user->id)
                ->where('reserved_at', '>=', now()->subDays(7))->exists();
            if ($previous) return false; // universal seven-day marketing frequency cap
            return DB::table('marketing_deliveries')->insertOrIgnore([
                'marketing_campaign_id' => $campaignId, 'user_id' => $user->id,
                'event_key' => $eventKey, 'status' => 'reserved',
                'reserved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]) === 1;
        }, 3);
    }

    public function suppressOnOptOut(User $user): int
    {
        return DB::table('marketing_deliveries')->where('user_id', $user->id)
            ->where('status', 'reserved')->update([
                'status' => 'suppressed', 'suppressed_at' => now(), 'updated_at' => now(),
            ]);
    }

    public function metrics(int $campaignId): array
    {
        $counts = DB::table('marketing_deliveries')->where('marketing_campaign_id', $campaignId)
            ->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        return ['reserved' => (int) ($counts['reserved'] ?? 0),
            'sent' => (int) ($counts['sent'] ?? 0),
            'suppressed' => (int) ($counts['suppressed'] ?? 0)];
    }
}

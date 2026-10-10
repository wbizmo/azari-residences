<?php

namespace App\Services\Travel;

use App\Models\TravelExperienceSlot;
use App\Models\TravelRequest;
use App\Models\TravelRequestEvent;
use App\Models\TravelSupplierWebhookEvent;
use App\Notifications\TravelRequestStatusNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class TravelSupplierEventProcessor
{
    /**
     * Safe under retries: provider+event unique, and inbox row marked
     * processed in the same DB transaction as the status/audit/notification.
     */
    public function process(int $limit = 50): array
    {
        $counts = ['processed' => 0, 'rejected' => 0];
        $ids = TravelSupplierWebhookEvent::query()->where('status', 'pending')
            ->orderBy('id')->limit(min(max($limit, 1), 100))->pluck('id');

        foreach ($ids as $id) {
            $status = DB::transaction(function () use ($id): ?string {
                $candidate = TravelSupplierWebhookEvent::query()->find($id);
                if (! $candidate || $candidate->status !== 'pending') {
                    return null;
                }

                $payload = json_decode(Crypt::decryptString($candidate->encrypted_body), true);
                $request = is_array($payload) && isset($payload['travel_request_id'])
                    ? TravelRequest::query()->find($payload['travel_request_id'])
                    : null;

                // Same lock order as guest cancellation: slot -> request -> inbox.
                if ($request?->travel_experience_slot_id) {
                    TravelExperienceSlot::query()->whereKey($request->travel_experience_slot_id)
                        ->lockForUpdate()->firstOrFail();
                }
                if ($request) {
                    $request = TravelRequest::query()->whereKey($request->id)
                        ->lockForUpdate()->firstOrFail();
                }
                $event = TravelSupplierWebhookEvent::query()->whereKey($id)
                    ->lockForUpdate()->firstOrFail();
                if ($event->status !== 'pending') {
                    return null;
                }

                $valid = $request
                    && $request->travel_supplier_id === $event->travel_supplier_id
                    && $request->supplier?->isApproved()
                    && in_array($payload['event_type'] ?? '', ['acknowledged','declined','disrupted'], true);

                $next = null;
                if ($valid && $request->status === 'requested' && $request->expires_at->isFuture()) {
                    if ($payload['event_type'] === 'acknowledged'
                        && $request->data_share_consent
                        && is_string($payload['supplier_reference'] ?? null)
                        && mb_strlen($payload['supplier_reference']) >= 5
                        && mb_strlen($payload['supplier_reference']) <= 160) {
                        $next = 'supplier_acknowledged';
                    } elseif ($payload['event_type'] === 'declined') {
                        $next = 'supplier_declined';
                    }
                } elseif ($valid && $request->status === 'supplier_acknowledged'
                    && in_array($payload['event_type'], ['declined','disrupted'], true)) {
                    $next = 'support_required';
                }

                // A late/out-of-order event is preserved as rejected evidence,
                // never silently rewinds a cancelled or expired travel request.
                if ($next === null) {
                    $event->update([
                        'status' => 'rejected', 'error_code' => 'invalid_transition_or_supplier',
                        'travel_request_id' => $valid ? $request->id : null,
                        'encrypted_body' => null,
                        'processed_at' => now(),
                    ]);
                    return 'rejected';
                }

                $before = $request->status;
                $request->update([
                    'status' => $next,
                    'supplier_reference' => $next === 'supplier_acknowledged'
                        ? $payload['supplier_reference'] : $request->supplier_reference,
                    'supplier_acknowledged_at' => $next === 'supplier_acknowledged'
                        ? now() : $request->supplier_acknowledged_at,
                ]);
                TravelRequestEvent::query()->create([
                    'travel_request_id' => $request->id,
                    'event_type' => 'supplier_webhook_'.$payload['event_type'],
                    'previous_status' => $before,
                    'new_status' => $next,
                    'details' => ['external_event_id' => $event->external_event_id],
                    'created_at' => now(),
                ]);

                $event->update([
                    'status' => 'processed', 'error_code' => null,
                    'travel_request_id' => $request->id,
                    'encrypted_body' => null, 'processed_at' => now(),
                ]);

                // Transactional database notification. If anything fails,
                // status and inbox remain pending so retries cannot lose it.
                $request->load('user');
                $request->user?->notify(new TravelRequestStatusNotification($request));
                return 'processed';
            }, 3);
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }
        return $counts;
    }
}

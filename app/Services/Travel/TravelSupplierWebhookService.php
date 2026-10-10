<?php

namespace App\Services\Travel;

use App\Models\TravelSupplier;
use App\Models\TravelSupplierWebhookEvent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TravelSupplierWebhookService
{
    /**
     * Authenticate before decoding or persisting, then deduplicate using a
     * database uniqueness constraint. No webhook writes a booking or payment.
     */
    public function capture(TravelSupplier $supplier, string $body, array $headers): array
    {
        if ($body === '' || strlen($body) > 65536 || ! json_validate($body)) {
            abort(413, 'Invalid or oversized travel supplier event.');
        }
        if (! $supplier->isApproved()) {
            throw new AccessDeniedHttpException('Supplier callbacks unavailable.');
        }

        $adapter = app(TravelPartnerGateway::class)->resolve($supplier);
        if (! $adapter->verifyWebhook($body, $headers)) {
            throw new AccessDeniedHttpException('Supplier callback authentication failed.');
        }

        $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($payload)
            || ! isset($payload['event_id'], $payload['event_type'], $payload['travel_request_id'])
            || ! is_string($payload['event_id'])
            || ! preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $payload['event_id'])
            || ! is_string($payload['event_type'])
            || ! in_array($payload['event_type'], ['acknowledged', 'declined', 'disrupted', 'cancelled'], true)
            || ! is_string($payload['travel_request_id'])
            || ! preg_match('/^[a-f0-9-]{36}$/iD', $payload['travel_request_id'])) {
            abort(422, 'Malformed supplier event envelope.');
        }

        $digest = hash('sha256', $body);
        return DB::transaction(function () use ($supplier, $payload, $body, $digest): array {
            $current = TravelSupplier::query()->whereKey($supplier->id)
                ->lockForUpdate()->firstOrFail();
            if (! $current->isApproved()) {
                throw new AccessDeniedHttpException('Supplier callbacks unavailable.');
            }

            // Envelope request ID is cross-checked during processing. No
            // implicit PII handoff or state change on receipt.
            $created = DB::table('travel_supplier_webhook_events')->insertOrIgnore([
                'travel_supplier_id' => $current->id,
                'travel_request_id' => null,
                'external_event_id' => $payload['event_id'],
                'body_sha256' => $digest,
                'encrypted_body' => Crypt::encryptString($body),
                'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $event = TravelSupplierWebhookEvent::query()
                ->where('travel_supplier_id', $current->id)
                ->where('external_event_id', $payload['event_id'])->firstOrFail();

            if (! hash_equals($event->body_sha256, $digest)) {
                throw new ConflictHttpException('Supplier event ID reused with different content.');
            }

            return ['accepted' => true, 'duplicate' => $created === 0];
        }, 3);
    }
}

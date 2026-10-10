<?php

namespace App\Services\PhaseThree;

use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\ChannelWebhookInbox;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Channels\ChannelAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deterministic reference processor for a local/sandbox provider contract.
 * No commercial provider may activate this handler or invent supplier truth.
 */
final class ChannelSandboxEventProcessor
{
    public function __construct(
        private readonly AzariAvailabilityEngine $availability,
        private readonly ChannelAvailabilityService $channels
    ) {}

    public function process(int $inboxId): string
    {
        try {
            return DB::transaction(function () use ($inboxId): string {
                $event = ChannelWebhookInbox::query()->whereKey($inboxId)->lockForUpdate()->firstOrFail();
                if (! in_array($event->status, ['received', 'retry'], true)) return $event->status;
                if ($event->next_attempt_at && $event->next_attempt_at->isFuture()) return 'retry';
                $connection = ChannelConnection::query()->whereKey($event->channel_connection_id)
                    ->lockForUpdate()->firstOrFail();
                if ($connection->provider !== 'resavar_sandbox'
                    || ! config('reserva.channels.sandbox_webhooks_enabled', false)
                    || ! $connection->is_active || in_array($connection->status,
                        ['disconnected', 'disconnected_pending_reconciliation'], true)) {
                    throw ValidationException::withMessages(['provider'=>'Sandbox provider no longer approved.']);
                }

                $payload = json_decode(Crypt::decryptString($event->encrypted_payload), true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) throw ValidationException::withMessages(['event'=>'Invalid event schema.']);
                $id = $payload['reservation_id'] ?? null;
                $version = $payload['version'] ?? null;
                $status = $payload['status'] ?? null;
                $start = $payload['starts_on'] ?? null;
                $end = $payload['ends_on'] ?? null;
                $quantity = $payload['quantity'] ?? null;
                if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $id)
                    || ! is_int($version) || $version < 1 || $version > 2147483647
                    || ! in_array($status, ['active','cancelled'], true)
                    || ! is_string($start) || ! is_string($end)
                    || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $start)
                    || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $end)
                    || ! is_int($quantity) || $quantity < 1 || $quantity > 1000
                    || ! $connection->accommodation_type_id
                    || ! $connection->accommodationType
                    || $connection->accommodationType->property_id !== $connection->property_id) {
                    throw ValidationException::withMessages(['event'=>'Malformed or unmapped supplier event.']);
                }
                try {
                    $checkIn = CarbonImmutable::createFromFormat('!Y-m-d', $start);
                    $checkOut = CarbonImmutable::createFromFormat('!Y-m-d', $end);
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['event'=>'Invalid supplier stay dates.']);
                }
                if (! $checkIn || ! $checkOut || $checkIn->format('Y-m-d') !== $start
                    || $checkOut->format('Y-m-d') !== $end || $checkIn->greaterThanOrEqualTo($checkOut)
                    || $checkIn->diffInDays($checkOut) > 366) {
                    throw ValidationException::withMessages(['event'=>'Invalid supplier stay interval.']);
                }
                $reservation = ChannelReservation::query()->where('channel_connection_id', $connection->id)
                    ->where('external_id', $id)->lockForUpdate()->first();
                $oldVersion = (int) data_get($reservation?->metadata, 'supplier_version', 0);
                if ($version <= $oldVersion) {
                    $event->forceFill(['status'=>'ignored','processed_at'=>now(), 'attempts'=>$event->attempts+1])->save();
                    return 'ignored';
                }
                $values = [
                    'property_id'=>$connection->property_id, 'accommodation_type_id'=>$connection->accommodation_type_id,
                    'starts_on'=>$start, 'ends_on'=>$end, 'status'=>$status, 'quantity'=>$quantity,
                    'summary'=>'External reservation', 'external_updated_at'=>$event->event_occurred_at,
                    'source_hash'=>$event->payload_sha256, 'last_seen_at'=>now(),
                    'metadata'=>['supplier_version'=>$version, 'supplier_event_id'=>$event->external_event_id],
                ];
                if ($reservation) $reservation->update($values);
                else $connection->reservations()->create(['external_id'=>$id, ...$values]);

                // Detect oversold individual nights, not just aggregated room-nights.
                // Supplier stays remain recorded and the whole channel fails closed.
                $conflict = false;
                if ($status === 'active' && $checkOut->greaterThan(CarbonImmutable::today())) {
                    $from = $checkIn->max(CarbonImmutable::today());
                    $until = $checkOut;
                    $committed = $this->availability->committedQuantityByDate(
                        $connection->accommodationType, $from, $until);
                    $external = $this->channels->blockedByDate($connection->accommodationType, $from, $until);
                    foreach ($external as $date => $blocked) {
                        if ((int) $blocked + (int) $committed->get($date, 0)
                            > (int) $connection->accommodationType->total_inventory) {
                            $conflict = true; break;
                        }
                    }
                }
                if ($conflict) {
                    $connection->forceFill(['status'=>'conflict','fail_closed'=>true,
                        'last_safe_error'=>'External supplier occupancy exceeds locally available rooms. Operator reconciliation required.'])->save();
                }
                $event->forceFill(['status'=>$conflict ? 'manual_review' : 'processed',
                    'attempts'=>$event->attempts+1,'processed_at'=>now(),
                    'safe_error'=>$conflict ? 'Supplier occupancy conflict. Review property inventory.' : null])->save();
                return $event->status;
            }, 3);
        } catch (ValidationException $exception) {
            ChannelWebhookInbox::query()->whereKey($inboxId)
                ->whereIn('status', ['received','retry'])->update([
                    'status'=>'manual_review', 'safe_error'=>'Supplier event requires manual reconciliation.',
                    'processed_at'=>now(), 'updated_at'=>now(),
                ]);
            return 'manual_review';
        } catch (\Throwable $exception) {
            // Do not record payload/secret or raw exception text in operator UI.
            DB::transaction(function () use ($inboxId): void {
                $event = ChannelWebhookInbox::query()->whereKey($inboxId)->lockForUpdate()->first();
                if (! $event || ! in_array($event->status,['received','retry'],true)) return;
                $attempt = $event->attempts + 1;
                $event->forceFill(['attempts'=>$attempt,'status'=>$attempt >= 5 ? 'dead_letter' : 'retry',
                    'next_attempt_at'=>$attempt >= 5 ? null : now()->addSeconds(min(3600, 30*2**$attempt)),
                    'safe_error'=>'Supplier event processing failed; review worker health.'])->save();
            }, 3);
            return 'retry_or_dead_letter';
        }
    }

    public function drain(int $limit = 25): array
    {
        $result = [];
        $ids = ChannelWebhookInbox::query()->whereIn('status', ['received','retry'])
            ->where(fn ($query) => $query->whereNull('next_attempt_at')
                ->orWhere('next_attempt_at','<=',now()))
            ->orderBy('id')->limit(max(1,min(100,$limit)))->pluck('id');
        foreach ($ids as $id) $result[$id] = $this->process((int) $id);
        return $result;
    }
}

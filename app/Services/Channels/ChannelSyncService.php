<?php

namespace App\Services\Channels;

use App\Models\AuditLog;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\ChannelSyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonImmutable;

class ChannelSyncService
{
    public function __construct(private readonly ChannelAdapterManager $adapters) {}

    public function sync(ChannelConnection $connection): ChannelSyncRun
    {
        // Never revive an operator-disconnected feed, even via a queued job.
        $current = $connection->fresh();
        if (! $current || ! $current->is_active
            || in_array($current->status, [
                ChannelConnectionLifecycleService::PENDING,
                ChannelConnectionLifecycleService::DISCONNECTED,
            ], true)) {
            return $connection->runs()->create([
                'status' => 'skipped', 'started_at' => now(), 'finished_at' => now(),
                'safe_error' => 'Channel is disconnected; manual reconciliation is required.',
            ]);
        }

        // A provider request must be single-flight across scheduler and
        // operator-triggered syncs. Otherwise the older response can arrive
        // last and overwrite a fresher calendar snapshot.
        $lock = Cache::lock('resavar:channel-sync:'.$connection->getKey(), 120);
        if (! $lock->get()) {
            return $connection->runs()->create([
                'status' => 'skipped', 'started_at' => now(), 'finished_at' => now(),
                'safe_error' => 'Another calendar synchronization is running.',
            ]);
        }

        try {
            $run = $connection->runs()->create(['status' => 'started', 'started_at' => now()]);
            $connection->forceFill(['last_attempted_at' => now(), 'status' => 'syncing'])->save();
            try {
                $events = $this->adapters->for($connection->provider)->import($connection);
                $counts = $this->applySnapshot($connection, $events);
                // A disconnect may commit after applySnapshot but before this
                // bookkeeping. Never overwrite its fail-closed status.
                $stillConnected = ChannelConnection::query()
                    ->whereKey($connection->getKey())
                    ->where('is_active', true)
                    ->whereNotIn('status', [
                        ChannelConnectionLifecycleService::PENDING,
                        ChannelConnectionLifecycleService::DISCONNECTED,
                    ])
                    ->update([
                        'status' => 'healthy', 'last_successful_sync_at' => now(),
                        'next_retry_at' => null, 'consecutive_failures' => 0,
                        'last_safe_error' => null,
                    ]);
                if (! $stillConnected) {
                    $run->update(['status' => 'skipped',
                        'safe_error' => 'Channel disconnected during synchronization.',
                        'finished_at' => now()]);
                    return $run->fresh();
                }
                $run->update([...$counts, 'status' => 'succeeded', 'finished_at' => now()]);
                AuditLog::record('channel.sync_succeeded', $connection, [], $counts);
            } catch (\Throwable $e) {
                $latest = $connection->fresh();
                if ($latest && in_array($latest->status, [
                    ChannelConnectionLifecycleService::PENDING,
                    ChannelConnectionLifecycleService::DISCONNECTED,
                ], true)) {
                    $run->update([
                        'status' => 'skipped', 'safe_error' => 'Channel was disconnected during synchronization.',
                        'finished_at' => now(),
                    ]);
                    return $run->fresh();
                }
                $failures = ((int) $connection->consecutive_failures) + 1;
                $minutes = min(360, 5 * (2 ** min(6, $failures - 1)));
                // Provider exception messages can contain signed calendar URLs.
                // Never surface them in operators' UI or general-purpose logs.
                $safe = 'Calendar synchronization failed; review the provider feed and retry.';
                $run->update(['status' => 'failed', 'safe_error' => $safe, 'finished_at' => now()]);
                $connection->forceFill([
                    'status' => 'failed', 'consecutive_failures' => $failures,
                    'next_retry_at' => now()->addMinutes($minutes), 'last_safe_error' => $safe,
                ])->save();
                Log::warning('Calendar import failed.', [
                    'connection_id' => $connection->getKey(),
                    'failure_class' => $e::class,
                ]);
            }

            return $run->fresh();
        } finally {
            $lock->release();
        }
    }

    /** @param array<int,array<string,mixed>> $events */
    public function applySnapshot(ChannelConnection $connection, array $events): array
    {
        return DB::transaction(function () use ($connection, $events): array {
            $lockedConnection = ChannelConnection::query()->whereKey($connection->getKey())
                ->lockForUpdate()->firstOrFail();
            if (! $lockedConnection->is_active || in_array($lockedConnection->status, [
                ChannelConnectionLifecycleService::PENDING,
                ChannelConnectionLifecycleService::DISCONNECTED,
            ], true)) {
                throw new \UnexpectedValueException('Disconnected provider snapshots cannot mutate inventory.');
            }

            // A truncated/invalid empty feed must not silently release every
            // external reservation. Legitimate empty snapshots need explicit
            // opt-in after an operator reviews the supplier calendar.
            if ($events === []
                && ! (bool) data_get($lockedConnection->settings, 'allow_empty_snapshot', false)
                && ChannelReservation::query()->where('channel_connection_id', $connection->getKey())
                    ->where('status', 'active')->exists()) {
                throw new \UnexpectedValueException('Empty channel snapshot requires manual confirmation before releasing existing reservations.');
            }

            $now = now(); $seen = []; $imported = 0; $updated = 0;
            foreach ($events as $event) {
                $externalId = trim((string) ($event['external_id'] ?? ''));
                if ($externalId === '' || strlen($externalId) > 255
                    || ! in_array((string) ($event['status'] ?? 'active'), ['active', 'cancelled'], true)
                    || empty($event['starts_on']) || empty($event['ends_on'])
                    || (string) $event['ends_on'] <= (string) $event['starts_on']) {
                    throw new \UnexpectedValueException('Invalid external reservation in calendar snapshot.');
                }
                if (in_array($externalId, $seen, true)) {
                    throw new \UnexpectedValueException('Duplicate reservation identifiers in external calendar snapshot.');
                }
                $seen[] = $externalId;
                $reservation = ChannelReservation::query()->where('channel_connection_id',$connection->id)->where('external_id',$externalId)->lockForUpdate()->first();
                $payload = [
                    'property_id'=>$connection->property_id,
                    'accommodation_type_id'=>$connection->accommodation_type_id,
                    'status'=>(string)($event['status'] ?? 'active'),
                    'starts_on'=>$event['starts_on'], 'ends_on'=>$event['ends_on'],
                    'quantity'=>max(1,(int)($event['quantity'] ?? 1)),
                    'summary'=>$event['summary'] ?? null,
                    'source_hash'=>$event['source_hash'] ?? null,
                    'external_updated_at'=>$event['external_updated_at'] ?? null,
                    'last_seen_at'=>$now,
                    'metadata'=>$event['metadata'] ?? null,
                ];
                if ($reservation) {
                    if ($reservation->external_updated_at && ! empty($event['external_updated_at'])
                        && CarbonImmutable::parse($event['external_updated_at'])->lessThan($reservation->external_updated_at)) {
                        // Stale provider sequence; keep the newer reservation.
                        continue;
                    }
                    $reservation->fill($payload);
                    if ($reservation->isDirty()) {
                        $reservation->save();
                        $updated++;
                    } else {
                        $reservation->forceFill(['last_seen_at'=>$now])->saveQuietly();
                    }
                } else {
                    $connection->reservations()->create(['external_id'=>$externalId, ...$payload]);
                    $imported++;
                }
            }
            $missing = ChannelReservation::query()
                ->where('channel_connection_id', $connection->id)
                ->where('status', 'active');
            if ($seen !== []) {
                $missing->whereNotIn('external_id', $seen);
            }
            $cancelled = 0;
            $allowEmpty = (bool) data_get($lockedConnection->settings, 'allow_empty_snapshot', false);
            foreach ($missing->lockForUpdate()->get() as $reservation) {
                $metadata = (array) ($reservation->metadata ?? []);
                $firstMissing = $metadata['missing_since'] ?? null;
                if (! $allowEmpty && ! $firstMissing) {
                    $metadata['missing_since'] = $now->toIso8601String();
                    $reservation->forceFill(['metadata' => $metadata])->save();
                    continue;
                }
                if (! $allowEmpty && CarbonImmutable::parse($firstMissing)->greaterThan(
                    $now->copy()->subMinutes(30)
                )) {
                    continue;
                }
                $reservation->forceFill(['status' => 'cancelled'])->save();
                $cancelled++;
            }
            return compact('imported','updated','cancelled');
        }, 3);
    }
}

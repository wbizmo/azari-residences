<?php

namespace App\Services\Channels;

use App\Models\AuditLog;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\ChannelSyncRun;
use Illuminate\Support\Facades\DB;

class ChannelSyncService
{
    public function __construct(private readonly ChannelAdapterManager $adapters) {}

    public function sync(ChannelConnection $connection): ChannelSyncRun
    {
        $run = $connection->runs()->create(['status'=>'started','started_at'=>now()]);
        $connection->forceFill(['last_attempted_at'=>now(),'status'=>'syncing'])->save();
        try {
            $events = $this->adapters->for($connection->provider)->import($connection);
            $counts = $this->applySnapshot($connection, $events);
            $run->update([...$counts, 'status'=>'succeeded','finished_at'=>now()]);
            $connection->forceFill(['status'=>'healthy','last_successful_sync_at'=>now(),'next_retry_at'=>null,'consecutive_failures'=>0,'last_safe_error'=>null])->save();
            AuditLog::record('channel.sync_succeeded', $connection, [], $counts);
        } catch (\Throwable $e) {
            $failures = ((int) $connection->consecutive_failures) + 1;
            $minutes = min(360, 5 * (2 ** min(6, $failures - 1)));
            $safe = (string) str($e->getMessage())->squish()->limit(220);
            $run->update(['status'=>'failed','safe_error'=>$safe,'finished_at'=>now()]);
            $connection->forceFill(['status'=>'failed','consecutive_failures'=>$failures,'next_retry_at'=>now()->addMinutes($minutes),'last_safe_error'=>$safe])->save();
            report($e);
        }
        return $run->fresh();
    }

    /** @param array<int,array<string,mixed>> $events */
    public function applySnapshot(ChannelConnection $connection, array $events): array
    {
        return DB::transaction(function () use ($connection, $events): array {
            ChannelConnection::query()->whereKey($connection->getKey())->lockForUpdate()->firstOrFail();
            $now = now(); $seen = []; $imported = 0; $updated = 0;
            foreach ($events as $event) {
                $externalId = (string) $event['external_id'];
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
                if ($reservation) { $reservation->fill($payload); if ($reservation->isDirty()) { $reservation->save(); $updated++; } else { $reservation->forceFill(['last_seen_at'=>$now])->saveQuietly(); } }
                else { $connection->reservations()->create(['external_id'=>$externalId, ...$payload]); $imported++; }
            }
            $cancelQuery = ChannelReservation::query()->where('channel_connection_id',$connection->id)->where('status','active');
            if ($seen !== []) $cancelQuery->whereNotIn('external_id',$seen);
            $cancelled = $cancelQuery->update(['status'=>'cancelled','updated_at'=>$now]);
            return compact('imported','updated','cancelled');
        }, 3);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\DiningRequest;
use App\Notifications\DiningArrivalReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SendDiningArrivalReminders extends Command
{
    protected $signature='resavar:dining-arrival-reminders {--limit=50}';
    protected $description='Send idempotent in-app reminders for independently confirmed dining reservations';

    public function handle(): int
    {
        if (! config('travel.dining_enabled',false)) return self::SUCCESS;
        $ids=DiningRequest::query()->where('status','confirmed')
            ->whereNotNull('provider_confirmed_at')
            ->whereNull('arrival_reminder_sent_at')
            ->whereBetween('requested_for',[now()->addHour(),now()->addHours(12)])
            ->orderBy('requested_for')->limit(min(max((int)$this->option('limit'),1),100))->pluck('id');
        $count=0;
        foreach($ids as $id) {
            $result=DB::transaction(function () use ($id): bool {
                $item=DiningRequest::query()->whereKey($id)->lockForUpdate()->first();
                if (! $item || $item->status!=='confirmed'
                    || $item->arrival_reminder_sent_at !== null
                    || $item->requested_for->lte(now()->addHour())
                    || $item->requested_for->gt(now()->addHours(12))) return false;
                $item->user->notify(new DiningArrivalReminder($item));
                $item->update(['arrival_reminder_sent_at'=>now()]);
                return true;
            },3);
            if ($result) $count++;
        }
        $this->components->info('Confirmed dining reminders: '.$count);
        return self::SUCCESS;
    }
}

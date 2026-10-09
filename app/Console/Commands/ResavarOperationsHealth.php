<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\ChannelConnection;
use App\Models\Refund;
use App\Models\SystemHeartbeat;
use App\Models\WithdrawalRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResavarOperationsHealth extends Command
{
    protected $signature = 'resavar:operations-health {--json : Output machine-readable checks}';
    protected $description = 'Verify marketplace background-processing readiness without exposing private provider data.';

    public function handle(): int
    {
        $now = now();
        $checks = [];
        $checks['database'] = DB::connection()->getPdo() !== null;
        $checks['scheduler'] = SystemHeartbeat::query()->where('component', 'scheduler')
            ->where('last_seen_at', '>=', $now->copy()->subMinutes(5))->exists();
        $checks['queue'] = SystemHeartbeat::query()->where('component', 'queue')
            ->where('last_seen_at', '>=', $now->copy()->subMinutes(5))->exists();
        $checks['failed_jobs'] = ! Schema::hasTable('failed_jobs')
            || DB::table('failed_jobs')->count() === 0;
        $checks['unreconciled_withdrawals'] = WithdrawalRequest::query()
            ->where('status', 'reconciliation_required')
            ->where('reconciliation_required_at', '<', $now->copy()->subHours(2))
            ->doesntExist();
        $checks['aged_refunds'] = Refund::query()
            ->whereIn('status', ['requested', 'processing'])
            ->where('requested_at', '<', $now->copy()->subDays(2))->doesntExist();
        $checks['stale_channels'] = ChannelConnection::query()->where('is_active', true)
            ->where('fail_closed', true)->get(['id', 'last_successful_sync_at', 'stale_after_minutes'])
            ->every(fn (ChannelConnection $channel): bool => $channel->last_successful_sync_at !== null
                && $channel->last_successful_sync_at->greaterThanOrEqualTo(
                    $now->copy()->subMinutes(max(1, (int) $channel->stale_after_minutes))
                ));
        $checks['verified_backup'] = BackupRun::query()->where('status', 'verified')
            ->where('verified_at', '>=', $now->copy()->subDay())->exists();

        if ($this->option('json')) {
            $this->line(json_encode(['healthy' => ! in_array(false, $checks, true), 'checks' => $checks], JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $name => $ok) {
                $this->line(($ok ? '[PASS] ' : '[FAIL] ').$name);
            }
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}

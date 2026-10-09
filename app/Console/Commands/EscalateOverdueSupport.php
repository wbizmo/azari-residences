<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\PremiumMailNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class EscalateOverdueSupport extends Command
{
    protected $signature = 'resavar:escalate-overdue-support {--limit=50}';
    protected $description = 'Alert authorized staff about overdue critical guest support cases';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 100));
        $critical = ['safety', 'unable_to_check_in', 'payment_taken_no_confirmation', 'property_unavailable'];
        $ids = SupportTicket::query()
            ->whereIn('severity', $critical)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->whereNull('sla_alerted_at')
            ->orderBy('sla_due_at')
            ->limit($limit)
            ->pluck('id');

        $queued = 0;
        foreach ($ids as $id) {
            try {
                $delivered = DB::transaction(function () use ($id, $critical): bool {
                    $ticket = SupportTicket::query()->whereKey($id)->lockForUpdate()->first();
                    if (! $ticket || ! in_array($ticket->severity, $critical, true)
                        || in_array($ticket->status, ['resolved', 'closed'], true)
                        || ! $ticket->sla_due_at || $ticket->sla_due_at->isFuture()
                        || $ticket->sla_alerted_at) {
                        return false;
                    }

                    $assigned = $ticket->assignee;
                    $recipients = $assigned && $assigned->is_active
                        && ($assigned->is_admin || filled($assigned->staff_role))
                        ? collect([$assigned])
                        : User::query()->where('is_admin', true)
                            ->where('is_active', true)->orderBy('id')->limit(3)->get();

                    if ($recipients->isEmpty()) {
                        return false;
                    }

                    foreach ($recipients as $recipient) {
                        $recipient->notify(new PremiumMailNotification(
                            'support-critical-sla',
                            'Urgent Resavar support case requires attention',
                            ['A critical support case has exceeded its response deadline.',
                                'Open the support queue and follow the escalation procedure.'],
                            'Open support case',
                            route('azari.admin.support.show', $ticket),
                            [
                                'support_ticket_id' => $ticket->id,
                                'dedupe_key' => 'critical-sla-'.$ticket->id,
                            ]
                        ));
                    }

                    $ticket->forceFill(['sla_alerted_at' => now()])->save();
                    AuditLog::record('support_ticket.sla_alert_queued', $ticket, [], [
                        'recipient_ids' => $recipients->pluck('id')->all(),
                        'severity' => $ticket->severity,
                    ]);

                    return true;
                }, 3);
                if ($delivered) {
                    $queued++;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $this->info("Critical support alerts queued: {$queued}");
        return self::SUCCESS;
    }
}

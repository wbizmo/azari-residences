<?php

namespace App\Observers;

use App\Models\SupportTicket;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class SupportTicketObserver
{
    use DispatchesAfterCommit;

    public function updated(SupportTicket $ticket): void
    {
        if (! $ticket->wasChanged('status')) {
            return;
        }

        $from = (string) $ticket->getOriginal('status');
        $to = (string) $ticket->status;

        if (! in_array($to, ['resolved', 'closed'], true)) {
            return;
        }

        // A resolved ticket may later be administratively closed. Do not send
        // a second terminal email for that same uninterrupted lifecycle.
        if ($to === 'closed' && $from === 'resolved' && $ticket->resolved_at !== null) {
            return;
        }

        $id = $ticket->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = SupportTicket::query()->with('user')->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->supportTicketCompleted($fresh);
            }
        });
    }
}

<?php

namespace App\Services\PhaseThree;

use App\Models\OwnerLedgerEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Read-only owner statements sourced from posted, immutable ledger rows. */
final class OwnerStatementService
{
    public function entries(User $owner, string $currency, CarbonImmutable $from, CarbonImmutable $through)
    {
        if (! preg_match('/^[A-Z]{3}$/', $currency) || $from->gt($through)
            || $from->diffInDays($through) > 366) {
            throw new \InvalidArgumentException('Invalid statement period or currency.');
        }
        return OwnerLedgerEntry::query()
            ->where('user_id', $owner->getKey())->where('currency', $currency)
            ->whereBetween('created_at', [$from, $through])
            ->orderBy('created_at')->orderBy('id')
            ->cursor();
    }

    public function totals(User $owner, string $currency, CarbonImmutable $from, CarbonImmutable $through): array
    {
        $entries = $this->entries($owner, $currency, $from, $through);
        $credit = $entries->filter(fn ($r) => $r->direction === 'credit')->sum(fn ($r) => (int) round((float) $r->amount * 100));
        $debit = $entries->filter(fn ($r) => $r->direction === 'debit')->sum(fn ($r) => (int) round((float) $r->amount * 100));
        return ['currency' => $currency, 'credit_minor' => $credit,
            'debit_minor' => $debit, 'net_minor' => $credit - $debit,
            'entries' => $entries->count(), 'provider_reconciled' => false];
    }
}

<?php

namespace App\Services\Owners;

use App\Models\OwnerLedgerEntry;
use App\Models\User;
use App\Models\WithdrawalRequest;

class OwnerBalanceService
{
    public function balance(User $user, string $currency): float
    {
        $credits = (float) OwnerLedgerEntry::query()
            ->where('user_id', $user->id)->where('currency', strtoupper($currency))
            ->where('direction', 'credit')->sum('amount');

        $debits = (float) OwnerLedgerEntry::query()
            ->where('user_id', $user->id)->where('currency', strtoupper($currency))
            ->where('direction', 'debit')->sum('amount');

        return round($credits - $debits, 2);
    }

    public function pending(User $user, string $currency): float
    {
        return round((float) WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->where('currency', strtoupper($currency))
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount'), 2);
    }

    public function available(User $user, string $currency): float
    {
        return max(0, round($this->balance($user, $currency) - $this->pending($user, $currency), 2));
    }
}

<?php

namespace App\Services\Owners;

use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\PaymentDispute;
use App\Models\Refund;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;

/**
 * Read-only internal ledger exception report. Provider settlement files are
 * separate evidence and are NOT inferred from internal payment status.
 */
class OwnerLedgerReconciliationService
{
    /** @return array<string,mixed> */
    public function inspect(User $owner, string $currency): array
    {
        $currency = strtoupper($currency);
        $ledger = OwnerLedgerEntry::query()
            ->where('user_id', $owner->getKey())->where('currency', $currency);

        $credits = round((float) (clone $ledger)->where('direction', 'credit')->sum('amount'), 2);
        $debits = round((float) (clone $ledger)->where('direction', 'debit')->sum('amount'), 2);

        $pending = round((float) WithdrawalRequest::query()
            ->where('user_id', $owner->getKey())->where('currency', $currency)
            ->whereIn('status', ['pending', 'processing', 'provider_sent', 'reconciliation_required'])
            ->sum('amount'), 2);

        $disputed = round((float) PaymentDispute::query()
            ->where('owner_user_id', $owner->getKey())->where('currency', $currency)
            ->where('status', 'open')->sum('amount'), 2);

        $balance = round($credits - $debits, 2);
        $available = round($balance - $pending - $disputed, 2);

        // Every successful owner-property charge must have a matching posted
        // booking_earning credit; this cannot trust public property display.
        $uncreditedPayments = Payment::query()
            ->where('status', Payment::SUCCESSFUL)
            ->where('currency', $currency)
            ->whereHas('booking.property', fn ($q) => $q
                ->where('owner_id', $owner->getKey())
                ->where('managed_for_owner', true))
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('owner_ledger_entries as owner_credit')
                    ->whereColumn('owner_credit.payment_id', 'payments.id')
                    ->where('owner_credit.type', 'booking_earning')
                    ->where('owner_credit.direction', 'credit');
            })->count();

        $unreversedRefunds = Refund::query()
            ->where('status', 'successful')->where('currency', $currency)
            ->whereHas('payment', fn ($payment) => $payment
                ->whereHas('booking.property', fn ($property) => $property
                    ->where('owner_id', $owner->getKey())
                    ->where('managed_for_owner', true)))
            ->whereHas('payment', fn ($payment) => $payment
                ->whereHas('booking.property', fn ($q) => $q
                    ->where('owner_id', $owner->getKey())))
            ->whereExists(function ($query) use ($owner): void {
                $query->selectRaw('1')->from('owner_ledger_entries as credit')
                    ->whereColumn('credit.payment_id', 'refunds.payment_id')
                    ->where('credit.type', 'booking_earning')
                    ->where('credit.user_id', $owner->getKey());
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('owner_ledger_entries as reversal')
                    ->whereColumn('reversal.refund_id', 'refunds.id')
                    ->where('reversal.type', 'refund_reversal')
                    ->where('reversal.direction', 'debit');
            })->count();

        $missingPayoutDebits = WithdrawalRequest::query()
            ->where('user_id', $owner->getKey())->where('currency', $currency)
            ->where('status', 'processed')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('owner_ledger_entries as debit')
                    ->whereColumn('debit.withdrawal_request_id', 'withdrawal_requests.id')
                    ->where('debit.type', 'withdrawal')
                    ->where('debit.direction', 'debit');
            })->count();

        $missingChargebackDebits = PaymentDispute::query()
            ->where('owner_user_id', $owner->getKey())->where('currency', $currency)
            ->where('status', 'lost')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('owner_ledger_entries as debit')
                    ->whereColumn('debit.payment_dispute_id', 'payment_disputes.id')
                    ->where('debit.type', 'chargeback_reversal')
                    ->where('debit.direction', 'debit');
            })->count();

        $duplicatePaymentCredits = (clone $ledger)->where('type', 'booking_earning')
            ->whereNotNull('payment_id')
            ->select('payment_id')
            ->groupBy('payment_id')->havingRaw('COUNT(*) > 1')->get()->count();

        $checks = [
            'non_negative_available_balance' => $available >= -0.005,
            'all_successful_owner_payments_credited' => $uncreditedPayments === 0,
            'all_successful_owner_refunds_reversed' => $unreversedRefunds === 0,
            'all_processed_withdrawals_debited' => $missingPayoutDebits === 0,
            'all_lost_disputes_reversed' => $missingChargebackDebits === 0,
            'no_duplicate_payment_credits' => $duplicatePaymentCredits === 0,
        ];

        return [
            'owner_id' => $owner->getKey(), 'currency' => $currency,
            'ledger_credits' => $credits, 'ledger_debits' => $debits,
            'posted_balance' => $balance,
            'pending_withdrawals' => $pending, 'dispute_reservations' => $disputed,
            'unreserved_available' => max(0, $available),
            'checks' => $checks, 'healthy' => ! in_array(false, $checks, true),
            'exceptions' => [
                'uncredited_payments' => $uncreditedPayments,
                'unreversed_refunds' => $unreversedRefunds,
                'processed_without_debit' => $missingPayoutDebits,
                'lost_disputes_without_debit' => $missingChargebackDebits,
                'duplicate_payment_credits' => $duplicatePaymentCredits,
            ],
            'provider_settlement_verified' => false,
            'note' => 'Local ledger only. Independently reconcile provider statements by currency and day.',
        ];
    }
}

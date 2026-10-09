<?php

namespace App\Services\Owners;

use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Str;

class OwnerEarningsService
{
    public function creditForPayment(Payment $payment): ?OwnerLedgerEntry
    {
        if (! $payment->isSuccessful()) {
            return null;
        }

        $payment->loadMissing('booking.property');
        $booking = $payment->booking;
        $property = $booking?->property;

        if (! $property?->managed_for_owner || ! $property->owner_id) {
            return null;
        }

        // Azari does not deduct a platform commission from owner-property
        // room sales. Future successful payments credit the full applicable
        // booking payment to the property owner.
        //
        // Historical ledger entries remain untouched because the ledger entry
        // below is idempotently keyed by payment_id.
        $share = 100.00;
        $grossAmount = round((float) $payment->amount, 2);
        $amount = $grossAmount;

        if ($amount <= 0) {
            return null;
        }

        $azariShare = 0.00;
        $azariAmount = 0.00;

        return OwnerLedgerEntry::query()->firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'user_id' => $property->owner_id,
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'type' => 'booking_earning',
                'direction' => 'credit',
                'amount' => $amount,
                'currency' => strtoupper((string) $payment->currency),
                'gross_amount' => $grossAmount,
                'owner_share_percentage' => $share,
                'azari_share_percentage' => $azariShare,
                'azari_share_amount' => $azariAmount,
                'reference' => 'EARN-'.Str::upper(Str::random(14)),
                'description' => 'Owner booking revenue credited from booking '.$booking->reference.'.',
                'metadata' => [
                    'payment_reference' => $payment->reference,
                    'property_name' => $property->name,
                    'share_snapshot' => [
                        'owner_percentage' => $share,
                        'owner_amount' => $amount,
                        'azari_percentage' => $azariShare,
                        'azari_amount' => $azariAmount,
                    ],
                ],
            ]
        );
    }
    /** Post exactly one refund debit, preserving the original owner's currency. */
    public function reverseForRefund(Refund $refund): ?OwnerLedgerEntry
    {
        if (! $refund->isSuccessful()) {
            return null;
        }

        $credit = OwnerLedgerEntry::query()->where('payment_id', $refund->payment_id)
            ->where('type', 'booking_earning')->where('direction', 'credit')->first();

        if (! $credit) {
            return null;
        }

        return OwnerLedgerEntry::query()->firstOrCreate(
            ['refund_id' => $refund->getKey()],
            [
                'user_id' => $credit->user_id,
                'property_id' => $credit->property_id,
                'booking_id' => $credit->booking_id,
                'type' => 'refund_reversal',
                'direction' => 'debit',
                'amount' => min((float) $credit->amount, (float) $refund->amount),
                'currency' => $credit->currency,
                'reference' => 'OWNER-RFD-'.$refund->getKey(),
                'description' => 'Refund '.$refund->reference.' reverses owner earnings.',
                'metadata' => [
                    'refund_reference' => $refund->reference,
                    'original_credit_id' => $credit->getKey(),
                ],
            ]
        );
    }

}

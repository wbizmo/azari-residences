<?php

namespace App\Services\Owners;

use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
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

        $share = max(0, min(100, (float) ($property->owner_share_percentage
            ?: config('azari.owners.default_owner_share_percentage', 88))));
        if ($share <= 0) {
            return null;
        }

        $grossAmount = round((float) $payment->amount, 2);
        $amount = round($grossAmount * ($share / 100), 2);
        if ($amount <= 0) {
            return null;
        }

        $azariShare = round(100 - $share, 2);
        $azariAmount = round($grossAmount - $amount, 2);

        return OwnerLedgerEntry::query()->firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'user_id' => $property->owner_id,
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'type' => 'booking_earning',
                'direction' => 'credit',
                'amount' => $amount,
                'currency' => (string) config('azari.currency', 'USD'),
                'gross_amount' => $grossAmount,
                'owner_share_percentage' => $share,
                'azari_share_percentage' => $azariShare,
                'azari_share_amount' => $azariAmount,
                'reference' => 'EARN-'.Str::upper(Str::random(14)),
                'description' => 'Owner share credited from booking '.$booking->reference.'.',
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
}

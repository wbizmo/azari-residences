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

        $share = max(0, min(100, (float) $property->owner_share_percentage));
        if ($share <= 0) {
            return null;
        }

        $amount = round((float) $payment->amount * ($share / 100), 2);
        if ($amount <= 0) {
            return null;
        }

        return OwnerLedgerEntry::query()->firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'user_id' => $property->owner_id,
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'type' => 'booking_earning',
                'direction' => 'credit',
                'amount' => $amount,
                'currency' => strtoupper($payment->currency),
                'gross_amount' => (float) $payment->amount,
                'owner_share_percentage' => $share,
                'reference' => 'EARN-'.Str::upper(Str::random(14)),
                'description' => 'Owner share credited from booking '.$booking->reference.'.',
                'metadata' => [
                    'payment_reference' => $payment->reference,
                    'property_name' => $property->name,
                ],
            ]
        );
    }
}

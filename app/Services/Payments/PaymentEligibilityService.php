<?php

namespace App\Services\Payments;

use App\Models\Booking;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentEligibilityService
{
    /**
     * Guard the public payment-selection page.
     *
     * Cancelled bookings are hidden with 404.
     * Fully paid bookings are rejected with 409.
     * Only active bookings with an outstanding balance may proceed.
     */
    public function assertCheckoutAccessible(Booking $booking): void
    {
        $this->assertNotCancelled($booking);

        if ($this->isFullyPaid($booking)) {
            throw new ConflictHttpException('This booking has already been fully paid.');
        }

        if (! $this->acceptsPayment($booking)) {
            throw new ConflictHttpException('This booking cannot accept a payment.');
        }
    }

    /**
     * Guard payment creation and retry at service level.
     *
     * This is intentionally called again after the booking row is locked,
     * preventing stale-page, duplicate-tab, and direct-route bypasses.
     */
    public function assertCanInitiate(Booking $booking): void
    {
        if ($this->isCancelled($booking)) {
            throw ValidationException::withMessages([
                'booking' => 'This booking has been cancelled and cannot accept payment.',
            ]);
        }

        if ($this->isFullyPaid($booking)) {
            throw ValidationException::withMessages([
                'booking' => 'This booking is already fully paid.',
            ]);
        }

        if (! $this->acceptsPayment($booking)) {
            throw ValidationException::withMessages([
                'booking' => 'This booking cannot accept a payment.',
            ]);
        }
    }

    public function acceptsPayment(Booking $booking): bool
    {
        if ($this->isCancelled($booking) || $this->isFullyPaid($booking)) {
            return false;
        }

        return ! in_array(
            strtolower((string) $booking->status),
            ['completed', 'checked_out', 'no_show'],
            true
        );
    }

    public function isCancelled(Booking $booking): bool
    {
        return filled($booking->cancelled_at)
            || strtolower((string) $booking->status) === 'cancelled';
    }

    public function isFullyPaid(Booking $booking): bool
    {
        /*
         * Supports both:
         * 1. Current verified Payment rows.
         * 2. Legacy/seeded paid bookings such as AZR-DEMO-001 that carry
         *    paid state directly on the booking record.
         */
        $legacyPaid = filled($booking->paid_at)
            || filled($booking->receipt_number)
            || filled($booking->payment_reference)
            || in_array(strtolower((string) $booking->status), ['paid', 'confirmed'], true);

        return $legacyPaid || $booking->balanceDue() <= 0;
    }

    private function assertNotCancelled(Booking $booking): void
    {
        if ($this->isCancelled($booking)) {
            throw new NotFoundHttpException;
        }
    }
}

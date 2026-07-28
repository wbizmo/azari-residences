<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditBookingPayments extends Command
{
    protected $signature = 'azari:booking-payment-audit
        {--strict : Fail for booking, reservation and payment regressions}';

    protected $description = 'Audit booking lifecycle, reservation consistency, payment reconciliation and concurrency protections.';

    public function handle(): int
    {
        $issues = [];

        $availability = File::get(app_path('Services/Bookings/AzariAvailabilityEngine.php'));
        foreach (['DB::transaction(', 'lockForUpdate()', 'BookingHold::query()'] as $needle) {
            if (! str_contains($availability, $needle)) {
                $issues[] = "Availability engine is missing {$needle}.";
            }
        }

        $creation = File::get(app_path('Services/Bookings/BookingCreationService.php'));
        foreach (['DB::transaction(', 'lockForUpdate()', '$lockedHold->delete()', 'BookingStatusHistory::query()->create('] as $needle) {
            if (! str_contains($creation, $needle)) {
                $issues[] = "Booking creation service is missing {$needle}.";
            }
        }

        $initiator = File::get(app_path('Services/Payments/PaymentInitiator.php'));
        foreach (['DB::transaction(', 'lockForUpdate()'] as $needle) {
            if (! str_contains($initiator, $needle)) {
                $issues[] = "Payment initiator is missing {$needle}.";
            }
        }

        $finalizer = File::get(app_path('Services/Payments/PaymentFinalizer.php'));
        foreach ([
            'PaymentVerificationAttempt::query()->create(',
            'reference_mismatch',
            'amount_mismatch',
            'currency_mismatch',
            "'successful_excess'",
            'lockForUpdate()',
            'OwnerEarningsService::class',
        ] as $needle) {
            if (! str_contains($finalizer, $needle)) {
                $issues[] = "Payment finalizer is missing {$needle}.";
            }
        }

        $attempt = strpos($finalizer, 'PaymentVerificationAttempt::query()->create(');
        $transaction = strpos($finalizer, 'return DB::transaction(');
        if ($attempt === false || $transaction === false || $attempt > $transaction) {
            $issues[] = 'Payment verification attempts must remain outside the successful-finalization transaction so rejected attempts survive rollback.';
        }

        $webhooks = File::get(app_path('Services/Payments/PaymentWebhookProcessor.php'));
        foreach ([
            'webhookSignatureIsValid',
            'PaymentEvent::query()->firstOrCreate(',
            'wasRecentlyCreated',
        ] as $needle) {
            if (! str_contains($webhooks, $needle)) {
                $issues[] = "Payment webhook processor is missing required duplicate/signature safeguard: {$needle}.";
            }
        }

        $booking = File::get(app_path('Models/Booking.php'));
        foreach (['isPaid()', 'canAcceptPayment()', 'balanceDue()', 'receiptAvailable()'] as $needle) {
            if (! str_contains($booking, $needle)) {
                $issues[] = "Booking payment lifecycle helper missing: {$needle}.";
            }
        }

        if (! File::exists(resource_path('views'))) {
            $issues[] = 'View layer missing; PDF and document workflows cannot be validated.';
        }

        $this->line('Booking/payment services scanned: 5');

        if ($issues !== []) {
            foreach (array_unique($issues) as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        $this->info('Phase 5 booking, reservations and payments audit passed.');

        return self::SUCCESS;
    }
}

<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PhaseThreeBCServiceTransactionTest extends TestCase
{
    public function test_phase_three_bc_audit_passes(): void
    {
        $this->artisan('azari:service-transaction-audit', ['--strict' => true])
            ->expectsOutputToContain('Phase 3B + 3C service and transaction audit passed.')
            ->assertSuccessful();
    }

    public function test_booking_creation_is_service_led(): void
    {
        $content = File::get(app_path('Http/Controllers/PublicSite/AzariBookingFlowController.php'));

        $this->assertStringContainsString('BookingCreationService', $content);
        $this->assertStringNotContainsString('DB::transaction(', $content);
        $this->assertStringNotContainsString('Booking::query()->create', $content);
    }

    public function test_critical_services_define_transactions_and_locks(): void
    {
        foreach ([
            'Bookings/BookingCreationService.php',
            'Bookings/AzariAvailabilityEngine.php',
            'Payments/PaymentInitiator.php',
            'Payments/PaymentFinalizer.php',
            'Owners/OwnerWithdrawalService.php',
        ] as $relative) {
            $content = File::get(app_path('Services/'.$relative));
            $this->assertStringContainsString('DB::transaction(', $content);
            $this->assertStringContainsString('lockForUpdate()', $content);
        }
    }

    public function test_payment_verification_attempt_is_recorded_before_finalization_transaction(): void
    {
        $content = File::get(app_path('Services/Payments/PaymentFinalizer.php'));
        $transaction = strpos($content, 'return DB::transaction(');
        $attempt = strpos($content, 'PaymentVerificationAttempt::query()->create(');

        $this->assertNotFalse($transaction);
        $this->assertNotFalse($attempt);
        $this->assertLessThan(
            $transaction,
            $attempt,
            'Verification attempts must remain outside the finalization transaction so failed verification is still audited.'
        );
    }

    public function test_withdrawal_claim_is_not_updated_unlocked(): void
    {
        $content = File::get(app_path('Services/Owners/OwnerWithdrawalService.php'));
        $this->assertStringNotContainsString('$claimed->update([', $content);
        $this->assertStringNotContainsString('$claimed->refresh()->update([', $content);
    }
}

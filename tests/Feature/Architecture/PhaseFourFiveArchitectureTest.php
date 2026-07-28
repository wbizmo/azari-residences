<?php

namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PhaseFourFiveArchitectureTest extends TestCase
{
    use RefreshDatabase;
    public function test_phase_four_model_database_audit_passes(): void
    {
        $this->artisan('azari:model-database-audit', ['--strict' => true])
            ->expectsOutputToContain('Phase 4 model, database and performance audit passed.')
            ->assertSuccessful();
    }

    public function test_phase_five_booking_payment_audit_passes(): void
    {
        $this->artisan('azari:booking-payment-audit', ['--strict' => true])
            ->expectsOutputToContain('Phase 5 booking, reservations and payments audit passed.')
            ->assertSuccessful();
    }

    public function test_booking_and_payment_relationships_are_defined(): void
    {
        $booking = File::get(app_path('Models/Booking.php'));
        $payment = File::get(app_path('Models/Payment.php'));

        foreach (['property(): BelongsTo', 'user(): BelongsTo', 'guests(): HasMany', 'payments(): HasMany'] as $needle) {
            $this->assertStringContainsString($needle, $booking);
        }

        foreach (['booking(): BelongsTo', 'verificationAttempts(): HasMany'] as $needle) {
            $this->assertStringContainsString($needle, $payment);
        }
    }

    public function test_payment_rejection_audit_survives_success_transaction_rollbacks(): void
    {
        $content = File::get(app_path('Services/Payments/PaymentFinalizer.php'));

        $this->assertLessThan(
            strpos($content, 'return DB::transaction('),
            strpos($content, 'PaymentVerificationAttempt::query()->create(')
        );
    }

    public function test_booking_and_payment_concurrency_guards_remain_present(): void
    {
        foreach ([
            'Services/Bookings/AzariAvailabilityEngine.php',
            'Services/Bookings/BookingCreationService.php',
            'Services/Payments/PaymentInitiator.php',
            'Services/Payments/PaymentFinalizer.php',
        ] as $relative) {
            $content = File::get(app_path($relative));
            $this->assertStringContainsString('DB::transaction(', $content);
            $this->assertStringContainsString('lockForUpdate()', $content);
        }
    }
}

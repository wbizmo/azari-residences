<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReconciliationExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_unfinished_provider_payment_is_abandoned_without_provider_polling(): void
    {
        config()->set('azari.integrations.reconcile_window_minutes', 60);

        $booking = Booking::factory()->create([
            'status' => 'pending_payment',
            'currency' => 'USD',
            'total' => 500,
        ]);

        $payment = Payment::query()->create([
            'reference' => 'PAY-STALE-001',
            'provider' => 'pesapal',
            'provider_reference' => 'OLD-TRACKING-ID',
            'booking_id' => $booking->id,
            'guest_email' => $booking->guest_email,
            'amount' => 500,
            'currency' => 'USD',
            'status' => 'pending',
            'initiated_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $result = app(PaymentReconciliationService::class)->reconcilePending(100);

        $this->assertSame(0, $result['checked']);
        $this->assertSame(1, $result['abandoned']);
        $this->assertSame('abandoned', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->abandoned_at);
        $this->assertDatabaseCount('payment_verification_attempts', 0);
    }

    public function test_failed_and_abandoned_payments_are_terminal_for_scheduled_polling(): void
    {
        config()->set('azari.integrations.reconcile_window_minutes', 1440);

        $booking = Booking::factory()->create([
            'status' => 'pending_payment',
            'currency' => 'USD',
            'total' => 500,
        ]);

        foreach (['failed', 'abandoned'] as $index => $status) {
            Payment::query()->create([
                'reference' => 'PAY-TERMINAL-00'.($index + 1),
                'provider' => 'pesapal',
                'provider_reference' => 'TERMINAL-'.$index,
                'booking_id' => $booking->id,
                'guest_email' => $booking->guest_email,
                'amount' => 250,
                'currency' => 'USD',
                'status' => $status,
                'initiated_at' => now()->subMinutes(10),
                'failed_at' => $status === 'failed' ? now()->subMinutes(5) : null,
                'abandoned_at' => $status === 'abandoned' ? now()->subMinutes(5) : null,
            ]);
        }

        $result = app(PaymentReconciliationService::class)->reconcilePending(100);

        $this->assertSame(0, $result['checked']);
        $this->assertSame(0, $result['abandoned']);
        $this->assertDatabaseCount('payment_verification_attempts', 0);
    }
}

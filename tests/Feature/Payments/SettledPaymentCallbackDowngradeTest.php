<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\PaymentFinalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettledPaymentCallbackDowngradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_failed_pending_or_mismatched_callback_cannot_reverse_successful_payment(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed']);
        foreach (['pending', 'failed', 'invalid'] as $variant) {
            $payment = Payment::query()->create([
                'booking_id' => $booking->getKey(),
                'status' => 'initiated',
                'amount' => 100,
                'currency' => 'NGN',
                'provider' => 'manual',
                'reference' => 'PAY-STALE-'.strtoupper($variant).'-'.uniqid(),
            ]);

            // Simulate an already-loaded callback model whose stale status
            // was read BEFORE another worker committed successful settlement.
            $stale = Payment::query()->findOrFail($payment->getKey());
            Payment::query()->whereKey($payment->getKey())->update([
                'status' => Payment::SUCCESSFUL, 'verified_at' => now(),
            ]);

            $result = app(PaymentFinalizer::class)->apply($stale, [
                'status' => $variant === 'invalid' ? 'successful' : $variant,
                'amount' => $variant === 'invalid' ? 99 : 100,
                'currency' => 'NGN',
                'merchant_reference' => $payment->reference,
                'provider_reference' => 'SAFE-SYNTHETIC-REFERENCE',
            ], 'webhook');

            $this->assertSame(Payment::SUCCESSFUL, $result->status);
            $this->assertSame(Payment::SUCCESSFUL, $payment->fresh()->status);
            $this->assertNotNull($payment->fresh()->verified_at);
        }
    }
}

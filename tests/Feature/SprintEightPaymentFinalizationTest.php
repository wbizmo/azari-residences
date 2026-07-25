<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentFinalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintEightPaymentFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_success_confirms_booking_once_and_is_idempotent(): void
    {
        $user = User::factory()->create(['account_type' => 'customer', 'is_active' => true]);
        $booking = Booking::factory()->for($user)->create(['status' => 'pending_payment', 'currency' => 'NGN', 'total' => 107500]);
        $payment = Payment::query()->create([
            'reference' => 'PAY-TEST-001', 'provider' => 'flutterwave', 'booking_id' => $booking->id,
            'user_id' => $user->id, 'guest_email' => $user->email, 'amount' => 107500,
            'currency' => 'NGN', 'status' => 'pending', 'initiated_at' => now(),
        ]);
        $verification = [
            'status' => 'successful', 'provider_status' => 'successful', 'provider_reference' => '443311',
            'merchant_reference' => 'PAY-TEST-001', 'amount' => 107500, 'currency' => 'NGN',
            'payment_method' => 'card', 'safe_response' => ['id' => 443311, 'status' => 'successful'],
        ];

        app(PaymentFinalizer::class)->apply($payment, $verification, 'test');
        app(PaymentFinalizer::class)->apply($payment->fresh(), $verification, 'duplicate-test');

        $this->assertSame(Payment::SUCCESSFUL, $payment->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('booking_status_histories', 1);
        $this->assertNotNull($payment->fresh()->receipt_number);
    }

    public function test_amount_or_currency_mismatch_is_rejected_without_confirming_booking(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending_payment', 'currency' => 'NGN', 'total' => 107500]);
        $payment = Payment::query()->create([
            'reference' => 'PAY-TEST-002', 'provider' => 'pesapal', 'booking_id' => $booking->id,
            'guest_email' => $booking->guest_email, 'amount' => 107500, 'currency' => 'NGN',
            'status' => 'pending', 'initiated_at' => now(),
        ]);

        app(PaymentFinalizer::class)->apply($payment, [
            'status' => 'successful', 'provider_status' => 'COMPLETED', 'provider_reference' => 'TRACK-2',
            'merchant_reference' => 'PAY-TEST-002', 'amount' => 100, 'currency' => 'USD',
            'safe_response' => ['payment_status_description' => 'COMPLETED'],
        ], 'test');

        $this->assertSame('invalid', $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->status);
        $this->assertDatabaseHas('payment_verification_attempts', ['payment_id' => $payment->id, 'result' => 'amount_mismatch']);
    }

    public function test_no_refund_routes_or_controller_actions_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->map(fn ($route) => ($route->getName() ?? '').' '.$route->uri());
        $this->assertFalse($routes->contains(fn (string $route) => str_contains(strtolower($route), 'refund')));
    }
}

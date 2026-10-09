<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Bookings\BookingCancellationQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationQuotePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_refundable_stay_within_free_cancellation_window_has_no_fee(): void
    {
        $stay = Booking::factory()->create([
            'total' => 1000, 'nightly_rate' => 500,
            'check_in' => now()->addDays(6), 'property_timezone' => 'Africa/Lagos',
            'policy_snapshot' => [
                'rate_plan' => ['is_refundable' => true],
                'cancellation' => ['free_cancel_hours' => 48, 'fee_percentage' => 50],
            ],
        ]);
        $quote = app(BookingCancellationQuoteService::class)->quote($stay);
        $this->assertSame(0.0, $quote['cancellation_fee']);
        $this->assertSame(0.0, $quote['maximum_refund_due']);
        $this->assertSame('not_initiated', $quote['refund_status']);
    }

    public function test_nonrefundable_stay_penalty_is_capped_to_stay_total(): void
    {
        $stay = Booking::factory()->create([
            'total' => 400, 'policy_snapshot' => ['rate_plan' => ['is_refundable' => false]],
        ]);
        $quote = app(BookingCancellationQuoteService::class)->quote($stay);
        $this->assertSame(400.0, $quote['cancellation_fee']);
        $this->assertSame(0.0, $quote['maximum_refund_due']);
    }

    public function test_partial_deposit_less_than_policy_penalty_gets_no_refund(): void
    {
        $stay = Booking::factory()->create([
            'total' => 100, 'currency' => 'NGN',
            'check_in' => now()->addDays(7),
            'policy_snapshot' => [
                'rate_plan' => ['is_refundable' => true],
                'cancellation' => ['fee_amount' => 40, 'free_cancel_hours' => 0],
            ],
        ]);
        Payment::query()->create([
            'booking_id' => $stay->id, 'status' => Payment::SUCCESSFUL,
            'amount' => 20, 'currency' => 'NGN', 'provider' => 'manual',
            'reference' => 'CANCEL-PARTIAL-DEPOSIT',
        ]);
        $quote = app(BookingCancellationQuoteService::class)->quote($stay);
        $this->assertEqualsWithDelta(40, $quote['cancellation_fee'], 0.001);
        $this->assertSame(0.0, $quote['maximum_refund_due']);
    }

    public function test_previous_settled_refund_does_not_reduce_original_entitlement(): void
    {
        $stay = Booking::factory()->create([
            'total' => 100, 'currency' => 'NGN',
            'check_in' => now()->addDays(7),
            'policy_snapshot' => [
                'rate_plan' => ['is_refundable' => true],
                'cancellation' => ['fee_amount' => 40, 'free_cancel_hours' => 0],
            ],
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $stay->id, 'status' => Payment::SUCCESSFUL,
            'amount' => 100, 'currency' => 'NGN', 'provider' => 'manual',
            'reference' => 'CANCEL-FULL-PAYMENT',
        ]);
        $payment->refunds()->create([
            'booking_id' => $stay->id,
            'reference' => 'CANCEL-SETTLED-FIRST',
            'amount' => 30, 'currency' => 'NGN',
            'provider' => 'manual', 'status' => 'successful',
        ]);
        $quote = app(BookingCancellationQuoteService::class)->quote($stay);
        $this->assertSame(100.0, $quote['gross_verified_paid']);
        $this->assertSame(70.0, $quote['net_paid']);
        $this->assertSame(60.0, $quote['maximum_refund_due']);
    }

    public function test_unknown_legacy_policy_does_not_invent_a_refund_amount(): void
    {
        $stay = Booking::factory()->create(['total' => 500, 'policy_snapshot' => []]);
        $quote = app(BookingCancellationQuoteService::class)->quote($stay);
        $this->assertTrue($quote['manual_review_required']);
        $this->assertNull($quote['maximum_refund_due']);
    }
}

<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Bookings\BookingCancellationSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CancellationSettlementFlowTest extends TestCase
{
    use RefreshDatabase;

    private function paidBooking(array $overrides = []): array
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(array_merge([
            'user_id' => $user->getKey(),
            'status' => 'confirmed',
            'total' => 1000,
            'nightly_rate' => 500,
            'check_in' => now()->addDays(7),
            'check_out' => now()->addDays(9),
            'property_timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
            'policy_snapshot' => [
                'rate_plan' => ['is_refundable' => true],
                'cancellation' => ['free_cancel_hours' => 48, 'fee_percentage' => 50],
            ],
        ], $overrides));
        $payment = Payment::query()->create([
            'booking_id' => $booking->getKey(), 'user_id' => $user->getKey(),
            'reference' => 'PAY-CANCEL-'.uniqid(), 'provider' => 'flutterwave',
            'amount' => 1000, 'currency' => 'NGN',
            'status' => Payment::SUCCESSFUL, 'verified_at' => now(),
        ]);
        return [$booking, $payment, $user];
    }

    public function test_guest_cancellation_creates_one_refund_request_and_replay_is_idempotent(): void
    {
        [$booking, $payment, $guest] = $this->paidBooking();
        $service = app(BookingCancellationSettlementService::class);
        $cancelled = $service->cancelForGuest($booking, $guest, 'Changed my travel plans');
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(1, $cancelled->statusHistory()->where('to_status', 'cancelled')->count());
        $refund = Refund::query()->where('payment_id', $payment->getKey())->firstOrFail();
        $this->assertSame('requested', $refund->status);
        $this->assertEqualsWithDelta(1000, (float) $refund->amount, 0.01);
        $this->assertSame(0, $service->reserveEligibleRefunds($booking->fresh())['refunds_requested']);
        $this->assertSame(1, Refund::query()->where('payment_id', $payment->getKey())->count());
        try {
            $service->cancelForGuest($booking->fresh(), $guest, 'Second cancellation');
            $this->fail('Already cancelled booking cannot transition twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking', $exception->errors());
        }
    }

    public function test_partial_deposit_below_cancellation_fee_cannot_be_refunded(): void
    {
        [$booking, $payment, $guest] = $this->paidBooking([
            'policy_snapshot' => [
                'rate_plan' => ['is_refundable' => true],
                'cancellation' => ['fee_amount' => 400],
            ],
        ]);
        $payment->forceFill(['amount' => 200])->save();
        $result = app(BookingCancellationSettlementService::class)
            ->cancelForGuest($booking, $guest, 'Changed my plans');
        $this->assertSame('cancelled', $result->status);
        $this->assertSame(0, $booking->refunds()->count());
        $this->assertSame(0, app(BookingCancellationSettlementService::class)
            ->reserveEligibleRefunds($booking->fresh())['refunds_requested']);
    }

    public function test_settled_refunds_count_against_original_entitlement_on_replay(): void
    {
        [$booking, $payment, $guest] = $this->paidBooking([
            'policy_snapshot' => [
                'rate_plan' => ['is_refundable' => true],
                'cancellation' => ['fee_amount' => 600],
            ],
        ]);
        $settlement = app(BookingCancellationSettlementService::class);
        $settlement->cancelForGuest($booking, $guest, 'Cancel this booking');
        $refund = $booking->refunds()->firstOrFail();
        $this->assertEqualsWithDelta(400, (float) $refund->amount, 0.01);

        // Simulate confirmed remote settlement by recording the terminal
        // evidence state. Reconciliation must not reserve again.
        $refund->forceFill(['status' => 'successful', 'processed_at' => now()])->save();
        $this->assertSame(0, $settlement->reserveEligibleRefunds($booking->fresh())['refunds_requested']);
        $this->assertSame(1, $booking->refunds()->count());
    }

    public function test_foreign_guest_cannot_cancel_booking(): void
    {
        [$booking] = $this->paidBooking();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BookingCancellationSettlementService::class)->cancelForGuest(
            $booking, User::factory()->create(), 'Unauthorized action'
        );
    }

    public function test_missing_legacy_cancellation_policy_never_invents_a_refund(): void
    {
        [$booking, $payment, $guest] = $this->paidBooking(['policy_snapshot' => []]);
        app(BookingCancellationSettlementService::class)->cancelForGuest(
            $booking, $guest, 'Need to cancel this booking'
        );
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(0, Refund::query()->where('payment_id', $payment->getKey())->count());
        $this->assertTrue(app(BookingCancellationSettlementService::class)
            ->reserveEligibleRefunds($booking->fresh())['manual_review']);
    }

    public function test_full_charge_no_show_does_not_promise_refund(): void
    {
        [$booking, $payment] = $this->paidBooking([
            'check_in' => today(), 'check_out' => today()->addDays(2),
            'policy_snapshot' => [
                'rate_plan' => ['is_refundable' => true],
                'cancellation' => [
                    'free_cancel_hours' => 48, 'fee_percentage' => 0, 'no_show_policy' => 'full_charge',
                ],
            ],
        ]);
        $staff = User::factory()->create([
            'is_admin' => true, 'staff_role' => 'administrator', 'account_type' => 'staff',
        ]);
        $service = app(BookingCancellationSettlementService::class);
        $noShow = $service->markNoShow($booking, $staff, 'Guest did not arrive');
        $this->assertSame('no_show', $noShow->status);
        $this->assertSame(1, $noShow->statusHistory()->where('to_status', 'no_show')->count());
        $this->assertSame(0, Refund::query()->where('payment_id', $payment->getKey())->count());
    }

    public function test_job_recovers_missing_refund_request_after_terminal_transition(): void
    {
        [$booking, $payment] = $this->paidBooking();
        app(\App\Services\Bookings\BookingCancellationService::class)->cancel(
            $booking, null, 'Admin cancelled during provider outage'
        );
        $this->assertSame(0, $booking->refunds()->count());
        $this->assertSame(0, Artisan::call('resavar:reconcile-cancellation-refunds'));
        $this->assertSame(1, Refund::query()->where('payment_id', $payment->getKey())->count());
        $this->assertSame(0, Artisan::call('resavar:reconcile-cancellation-refunds'));
        $this->assertSame(1, Refund::query()->where('payment_id', $payment->getKey())->count());
    }
}

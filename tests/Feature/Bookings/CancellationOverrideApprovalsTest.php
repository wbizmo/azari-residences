<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingCancellationOverride;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Bookings\BookingCancellationOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CancellationOverrideApprovalsTest extends TestCase
{
    use RefreshDatabase;

    private function setupCancelled(): array
    {
        $booking = Booking::factory()->create([
            'status' => 'cancelled',
            'currency' => 'NGN',
            'total' => 500,
            'cancelled_at' => now(),
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'status' => Payment::SUCCESSFUL,
            'currency' => 'NGN',
            'amount' => 500,
            'reference' => 'PAY-EXCEPTION-'.uniqid(),
            'provider' => 'manual',
            'verified_at' => now(),
        ]);
        $maker = User::factory()->create([
            'is_admin' => true, 'staff_role' => 'administrator', 'account_type' => 'staff',
        ]);
        $checker = User::factory()->create([
            'is_admin' => true, 'staff_role' => 'administrator', 'account_type' => 'staff',
        ]);
        return [$booking, $payment, $maker, $checker];
    }

    public function test_independent_checker_reserves_refund_once_without_marking_provider_paid(): void
    {
        [$booking, $payment, $maker, $checker] = $this->setupCancelled();
        $service = app(BookingCancellationOverrideService::class);
        $override = $service->request($booking, $maker, 125, 'Force-majeure compensation approved for review.');
        $this->assertSame('requested', $override->status);
        $this->assertCount(0, Refund::query()->get());

        try {
            $service->review($booking, $override, $maker, 'approve');
            $this->fail('Requesting staff member must not approve the same financial exception.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('reviewer', $error->errors());
        }

        $approved = $service->review($booking, $override, $checker, 'approve');
        $this->assertSame('applied', $approved->status);
        $refund = Refund::query()->where('payment_id', $payment->id)->firstOrFail();
        $this->assertSame('requested', $refund->status);
        $this->assertEqualsWithDelta(125, (float) $refund->amount, 0.001);

        $this->assertSame('applied', $service->review($booking, $override, $checker, 'approve')->status);
        $this->assertSame(1, Refund::query()->where('payment_id', $payment->id)->count());
        $this->assertSame(1, BookingCancellationOverride::query()->where('booking_id', $booking->id)->count());
    }

    public function test_override_cannot_exceed_verified_unreserved_balance(): void
    {
        [$booking, $payment, $maker, $checker] = $this->setupCancelled();
        Refund::query()->create([
            'booking_id' => $booking->id, 'payment_id' => $payment->id,
            'reference' => 'RFD-EXCEPTION-PREVIOUS',
            'amount' => 480, 'currency' => 'NGN',
            'status' => 'successful', 'provider' => 'manual',
            'requested_at' => now(),
        ]);
        $this->expectException(ValidationException::class);
        app(BookingCancellationOverrideService::class)->request(
            $booking, $maker, 100, 'Request exceeds the remaining verified funds.'
        );
    }

    public function test_requester_cannot_make_a_second_pending_exception(): void
    {
        [$booking, $payment, $maker] = $this->setupCancelled();
        $service = app(BookingCancellationOverrideService::class);
        $service->request($booking, $maker, 100, 'Reimbursement for property maintenance failure.');
        $this->expectException(ValidationException::class);
        $service->request($booking, $maker, 100, 'Duplicate pending exception must not be accepted.');
    }
}

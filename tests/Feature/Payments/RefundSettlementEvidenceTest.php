<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payments\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundSettlementEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_idempotency_key_cannot_be_reused_for_another_payment(): void
    {
        $booking = Booking::factory()->create();
        $first = Payment::query()->create([
            'booking_id' => $booking->id, 'status' => Payment::SUCCESSFUL,
            'amount' => 100, 'currency' => 'NGN', 'provider' => 'manual',
            'reference' => 'PAY-REF-001',
        ]);
        $second = Payment::query()->create([
            'booking_id' => $booking->id, 'status' => Payment::SUCCESSFUL,
            'amount' => 200, 'currency' => 'NGN', 'provider' => 'manual',
            'reference' => 'PAY-REF-002',
        ]);
        $service = app(RefundService::class);
        $firstRefund = $service->request($first, 25, null, 'Test refund', 'same-key');
        $this->assertSame($firstRefund->id, $service->request($first, 25, null, 'Retry', 'same-key')->id);
        try {
            $service->request($second, 25, null, 'Different payment', 'same-key');
            $this->fail('Cross-payment refund idempotency must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
        $this->assertSame(1, Refund::query()->count());
    }

    public function test_remote_refund_cannot_be_marked_successful_from_staff_form(): void
    {
        $staff = User::factory()->create([
            'account_type' => 'staff', 'staff_role' => 'administrator', 'is_admin' => true,
        ]);
        $booking = Booking::factory()->create();
        $payment = Payment::query()->create([
            'booking_id' => $booking->id, 'status' => Payment::SUCCESSFUL,
            'amount' => 100, 'currency' => 'NGN', 'provider' => 'flutterwave',
            'reference' => 'PAY-REF-003',
        ]);
        $refund = app(RefundService::class)->request($payment, 25, $staff->id, 'Test', 'provider-key');
        $this->actingAs($staff)
            ->patch(route('azari.admin.payments.refunds.update', [$payment, $refund]), [
                'action' => 'successful',
                'provider_reference' => 'unverified-operator-supplied-id',
            ])->assertSessionHasErrors('action');
        $this->actingAs($staff)
            ->patch(route('azari.admin.payments.refunds.update', [$payment, $refund]), [
                'action' => 'processing',
                'provider_reference' => 'unverified-operator-supplied-id',
            ])->assertSessionHasErrors('action');
        $this->assertSame('requested', $refund->fresh()->status);
    }
}

<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\ProviderStatementReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderStatementReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function statement(string $content, callable $assert): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rsv-settle-');
        file_put_contents($path, $content);
        try {
            $assert($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_matching_payment_and_refund_reconcile_without_financial_mutation(): void
    {
        $booking = Booking::factory()->create();
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'provider' => 'flutterwave',
            'provider_reference' => 'TX-SETTLED-123',
            'reference' => 'PAY-SETTLED-123',
            'status' => Payment::SUCCESSFUL,
            'amount' => 120,
            'currency' => 'NGN',
            'verified_at' => now(),
        ]);
        $refund = Refund::query()->create([
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'provider' => 'flutterwave',
            'provider_reference' => 'RFD-SETTLED-1',
            'reference' => 'RFD-LOCAL-SETTLED-1',
            'status' => 'successful',
            'amount' => 20,
            'currency' => 'NGN',
            'requested_at' => now(),
        ]);
        $this->statement(
            "type,reference,currency,amount,status\n".
            "payment,TX-SETTLED-123,NGN,120.00,settled\n".
            "refund,RFD-SETTLED-1,NGN,20.00,settled\n",
            function ($path) use ($payment, $refund): void {
                $result = app(ProviderStatementReconciliationService::class)->compare('flutterwave', $path);
                $this->assertTrue($result['statement_reconciled']);
                $this->assertSame(0, $result['exception_count']);
                $this->assertSame(10000, $result['matched_by_currency']['NGN']['net_minor_before_fees']);
                $this->assertSame(Payment::SUCCESSFUL, $payment->fresh()->status);
                $this->assertSame('successful', $refund->fresh()->status);
            }
        );
    }

    public function test_statement_mismatch_is_exception_not_automatic_settlement(): void
    {
        $booking = Booking::factory()->create();
        Payment::query()->create([
            'booking_id' => $booking->id, 'provider' => 'flutterwave',
            'provider_reference' => 'TX-SETTLED-999',
            'reference' => 'PAY-SETTLED-999',
            'status' => 'pending', 'amount' => 300,
            'currency' => 'NGN',
        ]);
        $this->statement(
            "type,reference,currency,amount,status\n".
            "payment,TX-SETTLED-999,NGN,300.00,settled\n".
            "payment,UNKNOWN,NGN,5.00,settled\n".
            "refund,UNVERIFIED,NGN,5.00,reversed\n",
            function ($path): void {
                $result = app(ProviderStatementReconciliationService::class)->compare('flutterwave', $path);
                $this->assertFalse($result['statement_reconciled']);
                $this->assertSame(3, $result['exception_count']);
                $this->assertSame(
                    ['not_successfully_recorded_locally', 'provider_reference_unmatched', 'provider_reversal_requires_review'],
                    array_column($result['exceptions'], 'reason')
                );
            }
        );
        $this->assertDatabaseHas('payments', [
            'provider_reference' => 'TX-SETTLED-999', 'status' => 'pending',
        ]);
    }

    public function test_malformed_csv_is_rejected_not_partially_accepted(): void
    {
        $this->statement("type,reference,currency,amount,status\npayment,TX,NGN,-20.00,settled\n", function ($path): void {
            $this->expectException(\InvalidArgumentException::class);
            app(ProviderStatementReconciliationService::class)->compare('flutterwave', $path);
        });
    }
}

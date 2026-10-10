<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportRefundServicingTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_case_refund_is_idempotent_and_never_claims_paid_provider_settlement(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $guest = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $guest->id]);
        $payment = Payment::query()->create([
            'reference' => 'PAY-SUPPORT-VERIFIED',
            'booking_id' => $booking->id,
            'user_id' => $guest->id,
            'provider' => 'flutterwave',
            'status' => 'successful',
            'amount' => 100000,
            'currency' => $booking->currency,
            'verified_at' => now(),
            'paid_at' => now(),
        ]);
        $ticket = SupportTicket::query()->create([
            'reference' => SupportTicket::nextReference(),
            'user_id' => $guest->id,
            'booking_id' => $booking->id,
            'status' => 'open',
            'severity' => 'general',
            'category' => 'payment',
            'subject' => 'Guest refund assistance',
        ]);

        $route = route('azari.admin.support.refund-request', $ticket);
        $form = [
            'payment_id' => $payment->id,
            'amount' => 12000,
            'reason' => 'Eligible partial refund following guest-reported room maintenance.',
        ];
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post($route, $form)
            ->assertRedirect(route('azari.admin.payments.show', $payment));
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post($route, $form)->assertRedirect();

        $this->assertSame(1, Refund::query()->where('payment_id', $payment->id)->count());
        $this->assertSame('requested', Refund::query()->firstOrFail()->status);
        $this->assertSame('escalated', $ticket->fresh()->status);
        $this->assertCount(1, $ticket->messages()->where('internal', true)->get());
    }

    public function test_finance_case_cannot_request_a_refund_for_an_unrelated_booking(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $guest = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $guest->id]);
        $other = Booking::factory()->create();
        $payment = Payment::query()->create([
            'reference' => 'PAY-UNRELATED',
            'booking_id' => $other->id,
            'user_id' => $guest->id,
            'provider' => 'flutterwave',
            'status' => 'successful',
            'amount' => 50000,
            'currency' => $other->currency,
            'verified_at' => now(),
        ]);
        $ticket = SupportTicket::query()->create([
            'reference' => SupportTicket::nextReference(),
            'user_id' => $guest->id, 'booking_id' => $booking->id,
            'status' => 'open', 'severity' => 'general',
            'category' => 'payment', 'subject' => 'Foreign payment',
        ]);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('azari.admin.support.refund-request', $ticket), [
            'payment_id' => $payment->id,
            'amount' => 1000,
            'reason' => 'This payment is unrelated to the current booking.',
        ])->assertNotFound();
        $this->assertDatabaseCount('refunds', 0);
    }
}

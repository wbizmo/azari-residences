<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingCheckoutResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_resumes_same_live_payment_without_creating_second_intent(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create(['user_id' => $guest->id, 'status' => 'pending_payment']);
        $payment = Payment::query()->create([
            'reference' => 'PAY-RESUME-EXAMPLE', 'booking_id' => $booking->id,
            'user_id' => $guest->id, 'provider' => 'paystack',
            'status' => 'pending', 'payment_kind' => 'full',
            'amount' => $booking->total, 'currency' => $booking->currency,
            'checkout_url' => 'https://checkout.paystack.com/existing',
            'initiated_at' => now(),
        ]);
        $count = Payment::query()->count();
        $this->actingAs($guest)->post(route('user.payments.resume', $payment))
            ->assertRedirect('https://checkout.paystack.com/existing');
        $this->assertSame($count, Payment::query()->count());
    }

    public function test_outsider_and_stale_payment_are_denied(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create(['user_id' => $guest->id, 'status' => 'pending_payment']);
        $payment = Payment::query()->create([
            'reference' => 'PAY-STALE-EXAMPLE', 'booking_id' => $booking->id,
            'user_id' => $guest->id, 'provider' => 'paystack',
            'status' => 'pending', 'amount' => $booking->total,
            'currency' => $booking->currency,
            'checkout_url' => 'https://checkout.paystack.com/expired',
            'initiated_at' => now()->subHour(),
        ]);
        $this->actingAs($other)->post(route('user.payments.resume', $payment))->assertForbidden();
        $this->actingAs($guest)->post(route('user.payments.resume', $payment))->assertUnprocessable();
    }
}

<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripsPaymentStatusParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_due_does_not_include_bookings_paid_after_failed_attempts_or_legacy_paid_bookings(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $paidProperty = Property::factory()->create(['name' => 'CONFIRMED_PAID_STAY']);
        $dueProperty = Property::factory()->create(['name' => 'OUTSTANDING_STAY']);
        $legacyProperty = Property::factory()->create(['name' => 'LEGACY_SETTLED_STAY']);

        $paid = Booking::factory()->create([
            'user_id' => $guest->id,
            'property_id' => $paidProperty->id,
            'status' => 'confirmed',
            'total' => 100000,
        ]);
        Payment::query()->create([
            'reference' => 'PAY-RETRY-FAILED',
            'booking_id' => $paid->id, 'user_id' => $guest->id,
            'provider' => 'flutterwave', 'status' => 'failed',
            'amount' => 100000, 'currency' => $paid->currency,
        ]);
        Payment::query()->create([
            'reference' => 'PAY-RETRY-SUCCESS',
            'booking_id' => $paid->id, 'user_id' => $guest->id,
            'provider' => 'flutterwave', 'status' => 'successful',
            'amount' => 100000, 'currency' => $paid->currency,
            'paid_at' => now(),
        ]);

        Booking::factory()->create([
            'user_id' => $guest->id,
            'property_id' => $dueProperty->id,
            'status' => 'pending_payment',
            'total' => 100000,
        ]);
        Booking::factory()->create([
            'user_id' => $guest->id,
            'property_id' => $legacyProperty->id,
            'status' => 'confirmed',
            'total' => 100000,
            'paid_at' => now(),
            'payment_reference' => 'LEGACY-PAYMENT-PROOF',
        ]);

        $response = $this->actingAs($guest)->get(
            route('user.bookings.index', ['status' => 'pending-payment'])
        );
        $response->assertOk()->assertSeeText('OUTSTANDING_STAY')
            ->assertDontSeeText('CONFIRMED_PAID_STAY')
            ->assertDontSeeText('LEGACY_SETTLED_STAY');
    }

    public function test_receipt_button_is_shown_only_for_bookings_with_payment_evidence(): void
    {
        $template = file_get_contents(resource_path('views/user/bookings/show.blade.php'));
        $this->assertStringContainsString('@if($receiptAvailable)', $template);
        $this->assertStringContainsString('Print payment receipt', $template);
        $this->assertStringContainsString('Invoice PDF', $template);
    }
}

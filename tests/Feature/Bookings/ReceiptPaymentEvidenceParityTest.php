<?php

namespace Tests\Feature\Bookings;

use App\Http\Controllers\UserArea\UserBookingController;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\Documents\BookingDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ReceiptPaymentEvidenceParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_booking_receipt_shows_successful_payment_not_latest_failed_attempt(): void
    {
        $guest = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $guest->id,
            'status' => 'confirmed',
            'total' => 100,
        ]);
        $paid = Payment::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $guest->id,
            'provider' => 'manual',
            'reference' => 'REC-PAY-SUCCESS-001',
            'status' => Payment::SUCCESSFUL,
            'currency' => $booking->currency,
            'amount' => 100,
            'paid_at' => now()->subHour(),
            'verified_at' => now()->subHour(),
        ]);
        Payment::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $guest->id,
            'provider' => 'manual',
            'reference' => 'REC-PAY-FAILED-001',
            'status' => Payment::FAILED,
            'currency' => $booking->currency,
            'amount' => 100,
            'created_at' => now(),
        ]);

        $this->actingAs($guest);
        $response = app(UserBookingController::class)->receipt(
            tap(\Illuminate\Http\Request::create('/user/receipt', 'GET'),
                fn ($request) => $request->setUserResolver(fn () => $guest)),
            $booking->reference
        );
        $view = $response->getOriginalContent();
        $this->assertSame($paid->getKey(), $view->getData()['payment']->getKey());
        $this->assertSame(Payment::SUCCESSFUL, $view->getData()['payment']->status);
    }

    public function test_customer_cannot_render_booking_receipt_with_only_failed_checkout(): void
    {
        $guest = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $guest->id,
            'status' => 'pending_payment',
            'paid_at' => null,
            'payment_reference' => null,
            'receipt_number' => null,
            'total' => 100,
        ]);
        Payment::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $guest->id,
            'provider' => 'manual',
            'reference' => 'REC-FAILED-ONLY-001',
            'status' => Payment::FAILED,
            'currency' => $booking->currency,
            'amount' => 100,
        ]);

        $this->actingAs($guest);
        $this->expectException(NotFoundHttpException::class);
        app(UserBookingController::class)->receipt(
            tap(\Illuminate\Http\Request::create('/user/receipt', 'GET'),
                fn ($request) => $request->setUserResolver(fn () => $guest)),
            $booking->reference
        );
    }

    public function test_booking_pdf_rejects_payment_from_another_booking(): void
    {
        $first = Booking::factory()->create();
        $other = Booking::factory()->create();
        $foreignPayment = Payment::query()->create([
            'booking_id' => $other->id,
            'provider' => 'manual',
            'reference' => 'REC-FOREIGN-PAYMENT-001',
            'status' => Payment::SUCCESSFUL,
            'currency' => $other->currency,
            'amount' => 100,
        ]);

        $this->expectException(NotFoundHttpException::class);
        app(BookingDocumentService::class)->render($first, 'receipt', $foreignPayment);
    }
}

<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingModificationRequest;
use App\Models\User;
use App\Services\Bookings\BookingModificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingModificationReviewSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_rejects_missing_required_details_and_conflicting_pending_revisions(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);
        $service = app(BookingModificationService::class);

        try {
            $service->request($booking, $user, 'date_change', []);
            $this->fail('Change requests with no dates are not actionable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('check_in', $exception->errors());
        }

        $first = $service->request($booking, $user, 'contact_details', ['email' => 'first@example.test']);
        $this->assertSame('pending', $first->status);

        $retry = $service->request($booking, $user, 'contact_details', ['email' => 'first@example.test']);
        $this->assertSame($first->id, $retry->id);

        try {
            $service->request($booking, $user, 'contact_details', ['email' => 'second@example.test']);
            $this->fail('A different change cannot replace a pending request silently.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }

        $this->assertSame(1, BookingModificationRequest::query()->where('booking_id', $booking->id)->count());
    }

    public function test_guest_cannot_open_modification_for_another_guests_booking(): void
    {
        $guest = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BookingModificationService::class)->request($booking, $guest, 'contact_details', ['email' => 'fraud@example.test']);
    }
}

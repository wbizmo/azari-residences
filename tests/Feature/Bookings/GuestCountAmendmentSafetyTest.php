<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\BookingGuestCountAmendmentService;
use App\Services\Bookings\BookingModificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuestCountAmendmentSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Mail::fake();
        $property = Property::factory()->create(['is_published' => true, 'status' => 'available']);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['max_guests' => 5, 'adult_capacity' => 4, 'child_capacity' => 2]);
        $guest = User::factory()->create();
        $reviewer = User::factory()->create(['is_admin' => true, 'staff_role' => 'administrator']);
        $booking = Booking::factory()->for($property)->create([
            'user_id' => $guest->getKey(), 'status' => 'confirmed',
            'accommodation_type_id' => $type->getKey(),
            'check_in' => now()->addDays(15), 'check_out' => now()->addDays(17),
            'rooms' => 1, 'adults' => 2, 'children' => 0, 'total' => 500,
        ]);
        foreach ([['first_name' => 'Ada', 'last_name' => 'Okoro', 'email' => 'ada@example.test', 'is_lead' => true],
                  ['first_name' => 'Emeka', 'last_name' => 'Okoro', 'email' => 'emeka@example.test', 'is_lead' => false]]
                 as $index => $person) {
            BookingGuest::query()->create([
                'booking_id' => $booking->getKey(), 'type' => 'adult',
                'position' => $index + 1, ...$person,
            ]);
        }
        return [$booking, $guest, $reviewer, $type];
    }

    public function test_additional_adult_is_registered_once_without_changing_original_price_or_lead_guest(): void
    {
        [$booking, $guest, $reviewer] = $this->fixture();
        $change = app(BookingModificationService::class)->request($booking, $guest, 'guest_change', [
            'adult_count' => 3, 'child_count' => 0,
            'new_adults' => [['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => 'ngozi@example.test']],
            'new_children' => [],
        ]);
        $reviewed = app(BookingGuestCountAmendmentService::class)->approve($booking, $change, $reviewer, 'Guest added.');
        $this->assertSame('approved', $reviewed->status);
        $this->assertSame(3, (int) $booking->fresh()->adults);
        $this->assertEqualsWithDelta(500, (float) $booking->fresh()->total, 0.01);
        $this->assertSame(3, $booking->guests()->where('type', 'adult')->count());
        $this->assertDatabaseHas('booking_guests', [
            'booking_id' => $booking->getKey(), 'position' => 3,
            'first_name' => 'Ngozi', 'email' => 'ngozi@example.test', 'is_lead' => 0,
        ]);
        $this->assertSame(1, $booking->guests()->where('is_lead', true)->count());

        $this->expectException(ValidationException::class);
        app(BookingGuestCountAmendmentService::class)->approve($booking->fresh(), $change->fresh(), $reviewer);
    }

    public function test_child_addition_keeps_existing_adult_identity_rows(): void
    {
        [$booking, $guest, $reviewer] = $this->fixture();
        $change = app(BookingModificationService::class)->request($booking, $guest, 'guest_change', [
            'adult_count' => 2, 'child_count' => 1, 'new_adults' => [],
            'new_children' => [['first_name' => 'Chidi', 'last_name' => 'Okoro']],
        ]);
        app(BookingGuestCountAmendmentService::class)->approve($booking, $change, $reviewer);
        $this->assertSame(1, (int) $booking->fresh()->children);
        $this->assertSame(2, $booking->guests()->where('type', 'adult')->count());
        $this->assertSame(1, $booking->guests()->where('type', 'child')->count());
    }

    public function test_over_capacity_amendment_is_rejected_without_mutation(): void
    {
        [$booking, $guest, $reviewer] = $this->fixture();
        $names = [];
        for ($i = 0; $i < 4; $i++) {
            $names[] = ['first_name' => 'Guest'.$i, 'last_name' => 'Okoro', 'email' => "guest{$i}@example.test"];
        }
        $change = app(BookingModificationService::class)->request($booking, $guest, 'guest_change', [
            'adult_count' => 6, 'child_count' => 0, 'new_adults' => $names,
        ]);
        try {
            app(BookingGuestCountAmendmentService::class)->approve($booking, $change, $reviewer);
            $this->fail('Over-capacity booking may not be approved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('guest_change', $exception->errors());
        }
        $this->assertSame(2, (int) $booking->fresh()->adults);
        $this->assertSame(2, $booking->guests()->count());
    }

    public function test_untrusted_reviewer_cannot_approve_another_booking_guests(): void
    {
        [$booking, $guest] = $this->fixture();
        $change = app(BookingModificationService::class)->request($booking, $guest, 'guest_change', [
            'adult_count' => 3, 'new_adults' => [
                ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => 'ngozi@example.test'],
            ],
        ]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BookingGuestCountAmendmentService::class)->approve($booking, $change, User::factory()->create());
    }
}

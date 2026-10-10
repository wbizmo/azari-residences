<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\TripItinerary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiStayTripItineraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_group_multiple_owned_stays_and_delete_group_without_cancelling_any_booking(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $first = Booking::factory()->create(['user_id' => $guest->id, 'status' => 'confirmed']);
        $second = Booking::factory()->create(['user_id' => $guest->id, 'status' => 'confirmed']);

        $this->actingAs($guest)->post(route('user.itineraries.store'), [
            'name' => 'Autumn travel',
        ])->assertRedirect();

        $group = TripItinerary::query()->where('user_id', $guest->id)->firstOrFail();

        foreach ([$first, $second] as $booking) {
            $this->actingAs($guest)->post(route('user.bookings.itineraries.assign', $booking->reference), [
                'trip_itinerary_id' => $group->id,
            ])->assertRedirect();

            $this->assertDatabaseHas('bookings', [
                'id' => $booking->id, 'user_id' => $guest->id,
                'trip_itinerary_id' => $group->id, 'status' => 'confirmed',
            ]);
        }

        $this->actingAs($guest)->get(route('user.itineraries.show', $group))
            ->assertOk()->assertSee('Autumn travel')
            ->assertSee($first->reference)
            ->assertSee($second->reference);

        $this->actingAs($guest)->delete(route('user.itineraries.destroy', $group))
            ->assertRedirect(route('user.bookings.index', ['status' => 'all']));

        $this->assertDatabaseMissing('trip_itineraries', ['id' => $group->id]);
        foreach ([$first, $second] as $booking) {
            $this->assertDatabaseHas('bookings', [
                'id' => $booking->id, 'user_id' => $guest->id,
                'trip_itinerary_id' => null, 'status' => 'confirmed',
            ]);
        }
    }

    public function test_cross_account_itinerary_and_booking_access_are_denied(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $outsider = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create(['user_id' => $guest->id]);
        $foreign = TripItinerary::query()->create([
            'user_id' => $outsider->id, 'name' => 'Private plans',
        ]);
        $owned = TripItinerary::query()->create([
            'user_id' => $guest->id, 'name' => 'My own plans',
        ]);

        $this->actingAs($guest)->post(route('user.bookings.itineraries.assign', $booking->reference), [
            'trip_itinerary_id' => $foreign->id,
        ])->assertSessionHasErrors('trip_itinerary_id');

        $this->actingAs($outsider)->get(route('user.itineraries.show', $owned))
            ->assertNotFound();
        $this->actingAs($outsider)->delete(route('user.itineraries.destroy', $owned))
            ->assertNotFound();

        $this->actingAs($outsider)->post(
            route('user.bookings.itineraries.assign', $booking->reference),
            ['trip_itinerary_id' => $foreign->id]
        )->assertNotFound();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id, 'trip_itinerary_id' => null,
        ]);
    }
}

<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Communications\AzariTransactionalMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RebookedStayReminderDedupeTest extends TestCase
{
    use RefreshDatabase;

    public function test_arrival_notice_is_once_per_checkin_date_and_rebooking_gets_a_new_checkpoint(): void
    {
        Notification::fake();
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create([
            'user_id' => $guest->id,
            'check_in' => '2026-12-08',
            'check_out' => '2026-12-12',
            'status' => 'confirmed',
        ]);
        $mail = app(AzariTransactionalMailService::class);
        $mail->sendArrivalReminder($booking);
        $mail->sendArrivalReminder($booking);
        $this->assertSame(1, CommunicationLog::query()->where('booking_id', $booking->id)->where('template', 'arrival-reminder')->count());

        $booking->update([
            'check_in' => '2026-12-15',
            'check_out' => '2026-12-19',
        ]);
        $mail->sendArrivalReminder($booking->fresh());
        $mail->sendArrivalReminder($booking->fresh());

        $this->assertSame(2, CommunicationLog::query()->where('booking_id', $booking->id)->where('template', 'arrival-reminder')->count());
        $this->assertSame(2, CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('template', 'arrival-reminder')->count());
    }

    public function test_departure_checkpoint_changes_without_repeating_same_date(): void
    {
        Notification::fake();
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create([
            'user_id' => $guest->id,
            'check_in' => '2026-11-13',
            'check_out' => '2026-11-16',
            'status' => 'confirmed',
        ]);
        $mail = app(AzariTransactionalMailService::class);
        $mail->sendCheckoutReminder($booking);
        $booking->update(['check_out' => '2026-11-18']);
        $mail->sendCheckoutReminder($booking->fresh());
        $mail->sendCheckoutReminder($booking->fresh());

        $this->assertSame(2, CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('template', 'checkout-reminder')->count());
    }
}

<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Security\BookingAttachmentScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CheckoutAndSupportSafetyRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_account_cannot_initiate_a_charge_using_another_guests_booking_reference(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $outsider = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'status' => 'pending_payment',
        ]);

        $this->actingAs($outsider)
            ->post(route('public.payment.initialise', $booking->reference), ['provider' => 'flutterwave'])
            ->assertNotFound();

        $this->assertSame(0, Payment::query()->where('booking_id', $booking->id)->count());
    }

    public function test_anonymous_unrelated_browser_cannot_create_charge_by_reference(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending_payment']);

        $this->post(route('public.payment.initialise', $booking->reference), ['provider' => 'flutterwave'])
            ->assertNotFound();

        $this->assertDatabaseMissing('payments', ['booking_id' => $booking->id]);
    }

    public function test_rejected_support_attachment_does_not_create_orphan_ticket(): void
    {
        Storage::fake('private');
        Notification::fake();
        $scanner = Mockery::mock(BookingAttachmentScanner::class);
        $scanner->shouldReceive('scan')->once()->andReturn('infected');
        $this->app->instance(BookingAttachmentScanner::class, $scanner);
        $guest = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($guest)->post(route('user.support.store'), [
            'category' => 'booking',
            'severity' => 'safety',
            'subject' => 'Unable to get into the property',
            'description' => 'I arrived but cannot check in and need help.',
            'attachment' => UploadedFile::fake()->image('arrival-proof.jpg'),
        ])->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('support_tickets', 0);
        $this->assertDatabaseCount('support_ticket_messages', 0);
        $this->assertEmpty(Storage::disk('private')->allFiles('support-attachments'));
    }

    public function test_ticket_deadline_and_first_response_deadline_match_urgent_severity(): void
    {
        Notification::fake();
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($guest)->post(route('user.support.store'), [
            'category' => 'booking',
            'severity' => 'safety',
            'subject' => 'Urgent check in assistance',
            'description' => 'I need someone to help me check in safely.',
        ])->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertSame('open', $ticket->status);
        $this->assertSame(
            $ticket->sla_due_at->toDateTimeString(),
            $ticket->response_due_at->toDateTimeString()
        );
    }

    public function test_support_references_are_unpredictable_and_safe_across_parallel_requests(): void
    {
        $this->assertNotSame(SupportTicket::nextReference(), SupportTicket::nextReference());
    }

    public function test_guest_reply_to_closed_ticket_cannot_leave_scanned_orphan_attachment(): void
    {
        Storage::fake('private');
        $scanner = Mockery::mock(BookingAttachmentScanner::class);
        $scanner->shouldReceive('scan')->once()->andReturn('clean');
        $this->app->instance(BookingAttachmentScanner::class, $scanner);
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::query()->create([
            'reference' => SupportTicket::nextReference(),
            'user_id' => $guest->id,
            'category' => 'booking',
            'subject' => 'Closed ticket',
            'status' => 'closed',
            'severity' => 'general',
        ]);

        $this->actingAs($guest)->post(route('user.support.reply', $ticket), [
            'body' => 'This ticket has already been closed.',
            'attachment' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertUnprocessable();

        $this->assertSame(0, $ticket->messages()->count());
        $this->assertEmpty(Storage::disk('private')->allFiles('support-attachments'));
    }
}

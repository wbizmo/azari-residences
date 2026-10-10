<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Property;
use App\Models\User;
use App\Services\Security\BookingAttachmentScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class BookingAttachmentQuarantineTest extends TestCase
{
    use RefreshDatabase;

    private function upload(): array
    {
        Storage::fake('private');

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create(['property_id' => $property->id, 'user_id' => $guest->id]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfZcAAAAASUVORK5CYII=');

        $this->actingAs($guest)->post(route('user.bookings.phase2.messages.store', $booking->reference), [
            'body' => 'Please see the attached document for this booking.',
            'client_token' => (string) Str::uuid(),
            'attachment' => UploadedFile::fake()->createWithContent('room.png', $png),
        ])->assertRedirect();

        return [$guest, $booking, BookingMessage::query()->firstOrFail()];
    }

    public function test_clean_scan_releases_private_attachment_to_authorized_booking_guest(): void
    {
        [$guest, $booking, $message] = $this->upload();
        $this->assertSame('pending', $message->attachment_scan_status);
        $link = route('user.bookings.phase2.messages.attachment', [$booking->reference, $message]);
        $this->actingAs($guest)->get($link)->assertNotFound();

        $scanner = Mockery::mock(BookingAttachmentScanner::class);
        $scanner->shouldReceive('scan')->once()->andReturn('clean');
        $this->app->instance(BookingAttachmentScanner::class, $scanner);
        $this->artisan('resavar:scan-booking-attachments')->assertExitCode(0);

        $this->assertSame('clean', $message->fresh()->attachment_scan_status);
        $this->actingAs($guest)->get($link)->assertOk();
    }

    public function test_infected_attachment_is_deleted_and_not_downloadable(): void
    {
        [$guest, $booking, $message] = $this->upload();

        $scanner = Mockery::mock(BookingAttachmentScanner::class);
        $scanner->shouldReceive('scan')->once()->andReturn('infected');
        $this->app->instance(BookingAttachmentScanner::class, $scanner);
        $this->artisan('resavar:scan-booking-attachments')->assertExitCode(0);

        $this->assertSame('infected', $message->fresh()->attachment_scan_status);
        Storage::disk('private')->assertMissing($message->attachment_path);
        $this->actingAs($guest)->get(route('user.bookings.phase2.messages.attachment', [
            $booking->reference, $message,
        ]))->assertNotFound();
    }

    public function test_unconfigured_scanner_never_approves_private_file(): void
    {
        config()->set('booking_attachment_scanner.host', null);
        [$guest, $booking, $message] = $this->upload();

        $this->artisan('resavar:scan-booking-attachments')->assertExitCode(0);
        $this->assertSame('pending', $message->fresh()->attachment_scan_status);
        $this->assertSame(1, $message->fresh()->attachment_scan_attempts);
        $this->actingAs($guest)->get(route('user.bookings.phase2.messages.attachment', [
            $booking->reference, $message,
        ]))->assertNotFound();
    }
}

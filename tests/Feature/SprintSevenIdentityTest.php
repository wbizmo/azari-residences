<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\IdentityType;
use App\Models\User;
use App\Services\Identity\IdentityDocumentService;
use Database\Seeders\AzariSprintSevenEightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SprintSevenIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(AzariSprintSevenEightSeeder::class);
    }

    public function test_account_identity_is_private_replaceable_and_attached_to_booking_owner(): void
    {
        $user = User::factory()->create(['account_type' => 'customer', 'is_active' => true]);
        $type = IdentityType::query()->where('slug', 'passport')->firstOrFail();
        $service = app(IdentityDocumentService::class);

        $first = $service->replaceUserIdentity($user, $type, UploadedFile::fake()->create('passport.pdf', 200, 'application/pdf'));
        Storage::disk('private')->assertExists($first->path);

        $second = $service->replaceUserIdentity($user, $type, UploadedFile::fake()->create('passport-new.pdf', 220, 'application/pdf'));
        $this->assertFalse($first->fresh()->is_current);
        $this->assertTrue($second->fresh()->is_current);
        $this->assertSame($first->id, $second->replaces_id);

        $booking = Booking::factory()->for($user)->create(['status' => 'pending_payment']);
        $lead = BookingGuest::query()->create(['booking_id' => $booking->id, 'type' => 'adult', 'position' => 1, 'first_name' => 'Lead', 'last_name' => 'Guest', 'is_lead' => true]);
        $link = $service->attachOwnerIdentity($booking, $user, $lead);

        $this->assertNotNull($link);
        $this->assertSame($second->id, $link->user_identity_document_id);
        $this->assertTrue($link->is_booking_owner);
    }

    public function test_each_additional_adult_can_receive_a_separate_identity(): void
    {
        $user = User::factory()->create(['account_type' => 'customer', 'is_active' => true]);
        $booking = Booking::factory()->for($user)->create(['status' => 'pending_payment']);
        $adult = BookingGuest::query()->create(['booking_id' => $booking->id, 'type' => 'adult', 'position' => 2, 'first_name' => 'Additional', 'last_name' => 'Adult', 'is_lead' => false]);

        $document = app(IdentityDocumentService::class)->storeGuestIdentity(
            $booking,
            $adult,
            'national_id',
            UploadedFile::fake()->create('national-id.pdf', 180, 'application/pdf'),
            $user->id,
        );

        Storage::disk('private')->assertExists($document->path);
        $this->assertDatabaseHas('booking_identity_links', [
            'booking_id' => $booking->id,
            'booking_guest_id' => $adult->id,
            'guest_identity_document_id' => $document->id,
        ]);
    }
}

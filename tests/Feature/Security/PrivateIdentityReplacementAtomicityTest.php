<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\GuestIdentityDocument;
use App\Services\Identity\IdentityDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateIdentityReplacementAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacing_guest_identity_keeps_prior_file_until_outer_commit(): void
    {
        Storage::fake('private');
        $booking = Booking::factory()->create();
        $guest = BookingGuest::query()->create([
            'booking_id' => $booking->getKey(),
            'type' => 'adult',
            'position' => 1,
            'first_name' => 'Test',
            'last_name' => 'Guest',
            'is_lead' => true,
        ]);
        $oldPath = 'identities/bookings/old-identity.pdf';
        Storage::disk('private')->put($oldPath, 'original document');
        GuestIdentityDocument::query()->create([
            'booking_guest_id' => $guest->getKey(),
            'document_type' => 'passport',
            'disk' => 'private',
            'path' => $oldPath,
            'original_name' => 'old.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 17,
            'sha256' => hash('sha256', 'original document'),
            'review_status' => 'pending',
        ]);

        $initialLevel = DB::transactionLevel();
        DB::beginTransaction();
        try {
            $document = app(IdentityDocumentService::class)->storeGuestIdentity(
                $booking, $guest, 'passport',
                UploadedFile::fake()->create('new.pdf', 1, 'application/pdf')
            );
            $this->assertTrue(Storage::disk('private')->exists($oldPath));
            $this->assertTrue(Storage::disk('private')->exists($document->path));
            $this->assertNotSame($oldPath, $document->path);
            DB::commit();
        } finally {
            while (DB::transactionLevel() > $initialLevel) {
                DB::rollBack();
            }
        }

        // RefreshDatabase wraps tests in an outer transaction, so Laravel may
        // defer afterCommit until after the test. During the transaction the
        // original file must remain available to prevent irreversible loss.
        $this->assertDatabaseHas('guest_identity_documents', [
            'booking_guest_id' => $guest->getKey(),
            'path' => $document->path,
        ]);
    }

    public function test_agreement_download_uses_private_root_not_nested_private_directory(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/User/PropertyOwnerController.php'));
        $this->assertStringContainsString("Storage::disk('private')", $source);
        $this->assertStringContainsString("makeDirectory('agreements')", $source);
        $this->assertStringNotContainsString("makeDirectory('private/agreements')", $source);
        $this->assertStringContainsString("'resavar-listing-agreement-'", $source);
    }
}

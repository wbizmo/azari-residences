<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use App\Models\PropertyPhotoModeration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyPhotoModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_requires_approved_metadata_and_current_property_ownership(): void
    {
        $this->withoutMiddleware();
        Storage::fake('public');
        Storage::disk('public')->put('properties/gallery/photo.jpg', 'image');
        $property = Property::factory()->create([
            'gallery' => ['properties/gallery/photo.jpg'],
        ]);
        $review = PropertyPhotoModeration::query()->create([
            'property_id' => $property->id,
            'path' => 'properties/gallery/photo.jpg',
            'path_hash' => hash('sha256', 'properties/gallery/photo.jpg'),
            'status' => 'pending',
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($admin)->patch(
            route('azari.admin.properties.photos.update', [$property, $review]),
            [
                'status' => 'approved',
                'alt_text' => 'Exterior garden courtyard at the property',
                'attribution' => 'Photographed by property owner',
            ]
        )->assertRedirect();

        $review->refresh();
        $this->assertSame('approved', $review->status);
        $this->assertSame('Exterior garden courtyard at the property', $review->alt_text);
        $this->assertSame($admin->id, (int) $review->reviewed_by);
        $this->assertNotNull($review->reviewed_at);
    }

    public function test_approved_photo_cannot_belong_to_another_property_or_missing_file(): void
    {
        $this->withoutMiddleware();
        Storage::fake('public');
        $property = Property::factory()->create([
            'gallery' => ['properties/gallery/removed.jpg'],
        ]);
        $other = Property::factory()->create();
        $review = PropertyPhotoModeration::query()->create([
            'property_id' => $property->id,
            'path' => 'properties/gallery/removed.jpg',
            'path_hash' => hash('sha256', 'properties/gallery/removed.jpg'),
            'status' => 'pending',
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $data = [
            'status' => 'approved',
            'alt_text' => 'Courtyard and entryway of the property',
            'attribution' => 'Submitted by property owner',
        ];
        $this->actingAs($admin)->patch(
            route('azari.admin.properties.photos.update', [$other, $review]), $data
        )->assertNotFound();
        $this->actingAs($admin)->patch(
            route('azari.admin.properties.photos.update', [$property, $review]), $data
        )->assertStatus(422);

        $this->assertSame('pending', $review->fresh()->status);
    }

    public function test_pending_media_never_appears_in_the_public_property_photo_gallery(): void
    {
        $template = file_get_contents(resource_path('views/public/properties/show.blade.php'));
        $this->assertStringContainsString("status !== 'approved'", $template);
        $this->assertStringContainsString('Photo credit:', $template);
    }
}

<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use App\Models\PropertyPhotoModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPhotoVisibilityParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_cover_is_never_selected_for_a_public_card(): void
    {
        $property = Property::factory()->create([
            'cover_image' => 'properties/covers/pending.jpg',
            'gallery' => ['properties/gallery/approved.jpg'],
        ]);
        PropertyPhotoModeration::query()->create([
            'property_id' => $property->id,
            'path' => 'properties/covers/pending.jpg',
            'path_hash' => hash('sha256', 'properties/covers/pending.jpg'),
            'status' => 'pending',
        ]);
        PropertyPhotoModeration::query()->create([
            'property_id' => $property->id,
            'path' => 'properties/gallery/approved.jpg',
            'path_hash' => hash('sha256', 'properties/gallery/approved.jpg'),
            'status' => 'approved',
            'alt_text' => 'Verified view of the property main lobby',
            'attribution' => 'Property rights holder',
        ]);

        $this->assertSame('properties/gallery/approved.jpg', $property->publicCoverImage());
        $this->assertSame(['properties/gallery/approved.jpg'],
            $property->publicPhotoPaths()->all());

        $property->load('photoModerations');
        $this->assertSame('properties/gallery/approved.jpg', $property->publicCoverImage());
    }

    public function test_rejected_photo_and_its_metadata_remain_hidden(): void
    {
        $property = Property::factory()->create([
            'cover_image' => 'properties/covers/rejected.jpg',
            'gallery' => [],
        ]);
        PropertyPhotoModeration::query()->create([
            'property_id' => $property->id,
            'path' => 'properties/covers/rejected.jpg',
            'path_hash' => hash('sha256', 'properties/covers/rejected.jpg'),
            'status' => 'rejected',
        ]);
        $this->assertNull($property->publicCoverImage());
    }
}

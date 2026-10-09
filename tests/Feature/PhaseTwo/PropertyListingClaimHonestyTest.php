<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class PropertyListingClaimHonestyTest extends TestCase
{
    public function test_property_page_never_invents_live_availability_or_verified_amenities(): void
    {
        $view = file_get_contents(resource_path('views/public/properties/show.blade.php'));
        $this->assertStringNotContainsString("'availability' => 'https://schema.org/InStock'", $view);
        $this->assertStringContainsString('These amenities are listed by the property.', $view);
        $this->assertStringContainsString('independently verified', $view);
        $this->assertStringContainsString("reviewSummary['count']", $view);
    }
}

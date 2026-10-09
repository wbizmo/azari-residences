<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class FlexibleDateQuoteDisplayTest extends TestCase
{
    public function test_date_suggestions_render_controller_quote_field_not_obsolete_key(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/PublicSite/AzariAvailabilityController.php'));
        $view = file_get_contents(resource_path('views/public/bookings/availability.blade.php'));

        $this->assertStringContainsString("'example_total' =>", $controller);
        $this->assertStringContainsString("\$alternative['example_total']", $view);
        $this->assertStringNotContainsString("\$alternative['from_total']", $view);
    }
}

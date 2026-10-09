<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class FlexibleDatePriceCalendarTest extends TestCase
{
    public function test_price_calendar_preserves_unavailable_days_without_inventing_rate(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/PublicSite/AzariAvailabilityController.php'));
        $blade = file_get_contents(resource_path('views/public/bookings/availability.blade.php'));
        $css = file_get_contents(resource_path('css/reserva/search-results.css'));

        $this->assertStringContainsString("'current' => true", $controller);
        $this->assertStringContainsString('->filter(fn ($item) => $item !== null)', $controller);
        $this->assertStringContainsString('Flexible-date price calendar', $blade);
        $this->assertStringContainsString("@if(\$alternative['example_total'] !== null)", $blade);
        $this->assertStringContainsString('No verified quote', $blade);
        $this->assertStringContainsString('Not selectable', $blade);
        $this->assertStringContainsString('aria-current="true"', $blade);
        $this->assertStringContainsString('reserva-flex-date-tile:focus-visible', $css);
    }
}

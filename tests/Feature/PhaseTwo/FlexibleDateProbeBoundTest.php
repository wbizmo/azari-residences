<?php

namespace Tests\Feature\PhaseTwo;

use App\Services\Search\MarketplaceSearchService;
use Tests\TestCase;

class FlexibleDateProbeBoundTest extends TestCase
{
    public function test_flexible_date_preview_uses_a_bounded_candidate_probe(): void
    {
        $method = new \ReflectionMethod(MarketplaceSearchService::class, 'search');
        $this->assertSame(3, $method->getNumberOfParameters());

        $controller = file_get_contents(app_path('Http/Controllers/PublicSite/AzariAvailabilityController.php'));
        $service = file_get_contents(app_path('Services/Search/MarketplaceSearchService.php'));

        $this->assertStringContainsString('$marketplace->search($candidateFilters, false, 5)', $controller);
        $this->assertStringContainsString('max(1, min($probePageSize, 30))', $service);
    }
}

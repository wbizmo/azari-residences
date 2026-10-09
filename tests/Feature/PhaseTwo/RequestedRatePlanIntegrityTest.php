<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class RequestedRatePlanIntegrityTest extends TestCase
{
    public function test_explicit_rate_selection_never_falls_back_to_another_plan(): void
    {
        $search = file_get_contents(app_path('Services/Search/MarketplaceSearchService.php'));
        $this->assertStringContainsString('if ($requestedRatePlanId && ! $ratePlan)', $search);
        $this->assertStringContainsString("['property' => \$property, 'unavailable' => true]", $search);
        $this->assertStringNotContainsString('?: $type->ratePlans->first()', $search);
    }
}

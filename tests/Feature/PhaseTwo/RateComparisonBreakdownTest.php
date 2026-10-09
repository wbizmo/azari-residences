<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class RateComparisonBreakdownTest extends TestCase
{
    public function test_property_rate_cards_use_canonical_quote_breakdown_instead_of_recalculating_money(): void
    {
        $view = file_get_contents(resource_path('views/public/properties/show.blade.php'));
        foreach ([
            "\$quote['total']",
            "\$quote['currency']",
            "\$quote['quantity']",
            "\$quote['fee_total']",
            "\$quote['tax_total']",
            "\$quote['discount_total']",
            "\$quote['security_deposit']",
            "\$quote['policy']['cancellation']",
        ] as $key) {
            $this->assertStringContainsString($key, $view);
        }
    }
}

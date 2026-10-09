<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class CheckoutCanonicalBreakdownTest extends TestCase
{
    public function test_checkout_uses_pricing_engine_line_items_and_discloses_separate_deposit(): void
    {
        $checkout = file_get_contents(resource_path('views/public/bookings/checkout.blade.php'));
        $comparison = file_get_contents(resource_path('views/public/properties/show.blade.php'));

        foreach ([
            "\$quote['subtotal_before_discount']",
            "\$quote['discount_total']",
            "\$quote['fee_breakdown']",
            "\$quote['tax_total']",
            "\$quote['total']",
            "\$addon['line_total']",
        ] as $key) {
            $this->assertStringContainsString($key, $checkout);
        }

        $this->assertStringContainsString('not included in the booking total', $checkout);
        $this->assertStringNotContainsString('Security deposit included:', $comparison);
    }
}

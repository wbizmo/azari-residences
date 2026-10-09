<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class SearchDateWindowLimitTest extends TestCase
{
    public function test_marketplace_rejects_stay_longer_than_the_booking_engine_limit(): void
    {
        $start = now()->addDays(3)->startOfDay();
        $this->getJson(route('availability.results', [
            'check_in' => $start->toDateString(),
            'check_out' => $start->copy()->addDays(367)->toDateString(),
            'adults' => 2,
            'rooms' => 1,
        ]))->assertUnprocessable()->assertJsonValidationErrors('check_out');
    }

    public function test_map_rejects_same_oversized_stay_as_list(): void
    {
        $start = now()->addDays(3)->startOfDay();
        $this->getJson(route('availability.map-points', [
            'check_in' => $start->toDateString(),
            'check_out' => $start->copy()->addDays(367)->toDateString(),
            'adults' => 2,
            'rooms' => 1,
        ]))->assertUnprocessable()->assertJsonValidationErrors('check_out');
    }
}

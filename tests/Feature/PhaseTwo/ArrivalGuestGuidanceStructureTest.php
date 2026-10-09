<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class ArrivalGuestGuidanceStructureTest extends TestCase
{
    public function test_arrival_ui_distinguishes_confirmed_instructions_and_noneditable_stays(): void
    {
        $view = file_get_contents(resource_path('views/user/bookings/arrival.blade.php'));
        $this->assertStringContainsString('property_timezone', $view);
        $this->assertStringContainsString('check_in_instructions', $view);
        $this->assertStringContainsString('nl2br(e(', $view);
        $this->assertStringContainsString('@if($arrivalEditable)', $view);
        $this->assertStringContainsString('can no longer be changed', $view);
    }
}

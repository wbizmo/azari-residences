<?php

namespace Tests\Feature\PhaseTwo;

use PHPUnit\Framework\TestCase;

class GuestOwnerVisualStructureTest extends TestCase
{
    public function test_owner_withdrawal_amount_has_full_width_accessible_field_and_exact_decimal_limits(): void
    {
        $view = file_get_contents(dirname(__DIR__, 3).'/resources/views/user/owner/withdrawals.blade.php');
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/reserva/authenticated.css');
        $this->assertStringContainsString('az-user-form--withdrawal', $view);
        $this->assertStringContainsString('id="owner-withdrawal-amount"', $view);
        $this->assertStringContainsString('aria-describedby="owner-withdrawal-hint"', $view);
        $this->assertStringContainsString("number_format((float)\$minimum, 2, '.', '')", $view);
        $this->assertStringContainsString("number_format((float)\$available, 2, '.', '')", $view);
        $this->assertStringContainsString('.az-user-form--withdrawal > *', $css);
        $this->assertStringContainsString('grid-template-columns: minmax(0,1fr) !important;', $css);
    }

    public function test_both_mobile_and_desktop_guest_navigation_omit_redundant_contact_item(): void
    {
        $partial = file_get_contents(dirname(__DIR__, 3).'/resources/views/user/partials/navigation.blade.php');
        $layout = file_get_contents(dirname(__DIR__, 3).'/resources/views/layouts/user.blade.php');
        $this->assertStringNotContainsString('routeIs(\'user.contact\')', $partial);
        $this->assertStringNotContainsString('<span>Contact Resavar</span>', $partial);
        $this->assertStringContainsString("@include('user.partials.navigation')", $layout);
    }

    public function test_address_lookup_has_accessible_loading_and_race_cancellation(): void
    {
        $component = file_get_contents(dirname(__DIR__, 3).'/resources/views/components/property-address-fields.blade.php');
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/reserva/authenticated.css');

        $this->assertStringContainsString('data-azari-address-status role="status" aria-live="polite"', $component);
        $this->assertStringContainsString('class="az-address-spinner"', $component);
        $this->assertStringContainsString('activeRequest?.abort();', $component);
        $this->assertStringContainsString('currentGeneration !== generation', $component);
        $this->assertStringContainsString("Address suggestions are unavailable.", $component);
        $this->assertStringContainsString('border-top-color: #052058;', $css);
    }

    public function test_guest_dashboard_actions_use_centered_icons_with_correct_text_fill(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/reserva/authenticated.css');
        $this->assertStringContainsString('.az-user-nav-link.is-active > .material-symbols-outlined', $css);
        $this->assertStringContainsString('.az-user-welcome .az-user-button--primary > .material-symbols-outlined', $css);
        $this->assertStringContainsString('.az-user-welcome .az-user-button--ghost > .material-symbols-outlined', $css);
        $this->assertStringContainsString('-webkit-text-fill-color: #FFFFFF !important;', $css);
        $this->assertStringContainsString('-webkit-text-fill-color: #052058 !important;', $css);
    }
}

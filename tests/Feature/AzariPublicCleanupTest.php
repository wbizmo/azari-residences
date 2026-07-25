<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AzariPublicCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_booking_verification_page_is_available(): void
    {
        $this->assertTrue(Route::has('bookings.verify'));

        $this->get(route('bookings.verify'))
            ->assertOk()
            ->assertSee('Verify booking');
    }

    public function test_homepage_links_directly_to_booking_verification_without_obsolete_popup(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee(route('bookings.verify'), false)
            ->assertDontSee('data-modal-open="verification-modal"', false)
            ->assertDontSee('id="verification-modal"', false)
            ->assertDontSee('available in Sprint 9');
    }

    public function test_public_footer_has_no_mailing_list_form(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Join the list')
            ->assertDontSee('newsletter-email', false)
            ->assertDontSee('footer-newsletter', false);
    }

    public function test_admin_booking_links_use_the_current_route_name(): void
    {
        $calendar = file_get_contents(
            resource_path('views/admin/bookings/availability-calendar.blade.php')
        );

        $sidebar = file_get_contents(
            resource_path('views/admin/partials/sidebar.blade.php')
        );

        $this->assertStringContainsString(
            'azari.admin.bookings.index',
            $calendar
        );

        $this->assertStringContainsString(
            'azari.admin.bookings.index',
            $sidebar
        );

        $this->assertStringNotContainsString(
            'azari.admin.s56.bookings.index',
            $calendar
        );

        $this->assertStringNotContainsString(
            'azari.admin.s56.bookings.index',
            $sidebar
        );
    }

    public function test_error_pages_share_the_common_layout(): void
    {
        $views = [
            '400',
            '401',
            '403',
            '404',
            '405',
            '408',
            '419',
            '422',
            '429',
            '500',
            '502',
            '503',
            '504',
            '4xx',
            '5xx',
            'minimal',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents(
                resource_path("views/errors/{$view}.blade.php")
            );

            $this->assertStringContainsString(
                "@extends('errors.layout')",
                $contents,
                "Error view {$view} must use the shared layout."
            );
        }
    }
}

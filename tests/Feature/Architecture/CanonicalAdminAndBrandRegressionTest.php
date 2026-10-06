<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class CanonicalAdminAndBrandRegressionTest extends TestCase
{
    public function test_legacy_admin_prefix_is_not_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $this->assertFalse($routes->contains(
            fn ($route) => str_starts_with($route->uri(), 'azari-admin')
        ));

        $this->assertTrue($routes->contains(
            fn ($route) => str_starts_with($route->uri(), 'azaridevadmin')
        ));
    }

    public function test_guest_navigation_does_not_expose_public_site_button(): void
    {
        $navigation = file_get_contents(resource_path('views/user/partials/navigation.blade.php'));

        $this->assertStringNotContainsString('Public site', $navigation);
    }

    public function test_public_footer_does_not_point_navigation_to_owner_create(): void
    {
        $footer = file_get_contents(resource_path('views/public/partials/footer.blade.php'));

        $this->assertStringNotContainsString("route('user.owner.listings.create')", $footer);
    }

    public function test_brand_component_uses_light_and_dark_logo_assets_without_masking(): void
    {
        $brand = file_get_contents(resource_path('views/components/brand-logo.blade.php'));

        $this->assertStringContainsString("siteName = 'Resarva'", $brand);
        $this->assertStringContainsString('logo-light.png', $brand);
        $this->assertStringContainsString('logo-dark.png', $brand);
        $this->assertStringNotContainsString('azari-logo-mask', $brand);
    }

    public function test_primary_brand_matches_reserva_identity(): void
    {
        $this->assertSame('Resarva', config('app.name'));

        $email = file_get_contents(resource_path('views/emails/premium.blade.php'));
        $this->assertStringContainsString('#0577F5', $email);
        $this->assertStringContainsString('Resarva', $email);
        $this->assertStringNotContainsString('Resavar', $email);
        $this->assertStringContainsString('#052058', $email);
        $this->assertStringContainsString('#F58F07', $email);
        $this->assertStringContainsString('Exceptional Stays, Everywhere.', $email);
    }

    public function test_reserva_brand_authority_uses_documented_palette_and_typography(): void
    {
        $brand = file_get_contents(resource_path('css/reserva/tokens.css'));

        foreach (['#052058', '#0577F5', '#F58F07', '#EEF2F8', '#FFFFFF', 'Montserrat'] as $token) {
            $this->assertStringContainsString($token, $brand);
        }

        foreach (['#2596be', '#176b89', '#eaf7fb'] as $obsolete) {
            $this->assertStringNotContainsString($obsolete, $brand);
        }
    }

    public function test_public_brand_positioning_uses_reserva_name(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/public.blade.php'));

        $this->assertStringContainsString('Resarva | Luxury Hotels, Residences, Apartments, Rooms and Hospitality', $layout);
        $this->assertStringContainsString('Resarva Luxury Properties Ltd', $layout);
        $this->assertStringNotContainsString('Azari Hotels & Residences', $layout);
        $this->assertStringNotContainsString('Resavar', $layout);
    }
}

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

    public function test_brand_component_uses_one_logo_source_and_masking(): void
    {
        $brand = file_get_contents(resource_path('views/components/brand-logo.blade.php'));

        $this->assertStringContainsString('azari-logo-mask', $brand);
        $this->assertStringNotContainsString('light_logo_url', $brand);
        $this->assertStringNotContainsString('logo-light.png', $brand);
    }
    public function test_primary_brand_matches_resavar_identity_guide(): void
    {
        $this->assertSame('Resavar', config('app.name'));

        $email = file_get_contents(resource_path('views/emails/premium.blade.php'));
        $this->assertStringContainsString('#0577F5', $email);
        $this->assertStringContainsString('Resavar', $email);
        $this->assertStringContainsString('#052058', $email);
        $this->assertStringContainsString('#F58F07', $email);
        $this->assertStringContainsString('Exceptional Stays, Everywhere.', $email);
        $this->assertStringNotContainsString('#eee6d7', $email);
    }


    public function test_resavar_brand_authority_uses_documented_palette_and_typography(): void
    {
        $brand = file_get_contents(resource_path('css/azari-brand-2026.css'));

        foreach (['#052058', '#0577F5', '#F58F07', '#EEF2F8', '#FFFFFF', 'Montserrat'] as $token) {
            $this->assertStringContainsString($token, $brand);
        }

        foreach (['#2596be', '#176b89', '#eaf7fb'] as $obsolete) {
            $this->assertStringNotContainsString($obsolete, $brand);
        }
    }

    public function test_public_brand_positioning_uses_resavar_name_and_exact_tagline(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/public.blade.php'));

        $this->assertStringContainsString('RESAVAR | Exceptional Stays, Everywhere.', $layout);
        $this->assertStringContainsString('global accommodation and travel marketplace', $layout);
        $this->assertStringNotContainsString('Azari Hotels & Residences', $layout);
        $this->assertStringNotContainsString('Reserva', $layout);
    }


}

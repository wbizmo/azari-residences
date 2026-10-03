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
    public function test_primary_brand_is_reserva_and_email_palette_is_blue_white(): void
    {
        $this->assertSame('Reserva', config('app.name'));

        $email = file_get_contents(resource_path('views/emails/premium.blade.php'));
        $this->assertStringContainsString('#2596be', $email);
        $this->assertStringContainsString('Reserva', $email);
        $this->assertStringNotContainsString('#103d33', $email);
        $this->assertStringNotContainsString('#eee6d7', $email);
    }


}

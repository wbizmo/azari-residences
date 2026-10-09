<?php

namespace Tests\Feature\Architecture;

use App\Models\SiteSetting;
use App\Support\Azari;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResavarBrandNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_constant_and_defaults_use_resavar(): void
    {
        $this->assertSame('Resavar', Azari::NAME);
        $this->assertStringContainsString(
            "'Resavar'",
            file_get_contents(app_path('Providers/AppServiceProvider.php'))
        );
    }

    public function test_migration_repairs_only_known_old_brand_defaults(): void
    {
        SiteSetting::put('site_name', 'Resarva', 'text', 'branding');
        SiteSetting::put('business_name', 'Reserva Luxury Properties Ltd', 'text', 'branding');
        SiteSetting::put('seo_title', 'Resarva | Exceptional serviced stays', 'text', 'seo');
        SiteSetting::put('seo_description', 'Custom operator SEO description', 'text', 'seo');

        $migration = require database_path('migrations/2026_10_09_233000_normalize_resavar_brand_settings.php');
        $migration->up();

        $this->assertSame('Resavar', SiteSetting::valueFor('site_name'));
        $this->assertSame('Resavar Luxury Properties Ltd', SiteSetting::valueFor('business_name'));
        $this->assertSame('Resavar | Exceptional serviced stays', SiteSetting::valueFor('seo_title'));
        $this->assertSame('Custom operator SEO description', SiteSetting::valueFor('seo_description'));

        // Idempotent, safe to run twice without changing later CMS edits.
        SiteSetting::put('site_name', 'Custom Boutique Stay');
        $migration->up();
        $this->assertSame('Custom Boutique Stay', SiteSetting::valueFor('site_name'));
    }

    public function test_guest_button_fill_colors_are_set_explicitly_against_legacy_css(): void
    {
        $css = file_get_contents(resource_path('css/reserva/authenticated.css'));

        $this->assertStringContainsString('.az-user-welcome .az-user-button--primary', $css);
        $this->assertStringContainsString('.az-user-welcome .az-user-button--ghost', $css);
        $this->assertStringContainsString('-webkit-text-fill-color: var(--reserva-navy) !important;', $css);
        $this->assertStringContainsString('-webkit-text-fill-color: var(--reserva-white) !important;', $css);
    }
}

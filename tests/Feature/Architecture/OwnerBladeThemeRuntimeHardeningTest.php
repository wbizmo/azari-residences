<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OwnerBladeThemeRuntimeHardeningTest extends TestCase
{
    public function test_owner_navigation_contains_real_balanced_blade_guards(): void
    {
        $source = file_get_contents(resource_path('views/user/partials/navigation.blade.php'));

        $this->assertStringContainsString("@if(Route::has('user.owner.dashboard'))", $source);
        $this->assertDoesNotMatchRegularExpression(
            "/^[ \t]*\\(Route::has\\('user\\.owner/m",
            $source
        );
        $this->assertSame(
            substr_count($source, '@if'),
            substr_count($source, '@endif'),
            'User navigation Blade conditionals must remain balanced.'
        );
    }

    public function test_all_owner_views_use_the_correct_application_shell(): void
    {
        foreach (glob(resource_path('views/user/owner/*.blade.php')) as $path) {
            if (str_ends_with($path, 'agreement-pdf.blade.php')) {
                continue;
            }
            $this->assertStringContainsString("@extends('layouts.user')", file_get_contents($path), $path);
        }

        foreach ([
            resource_path('views/admin/owner-listings/*.blade.php'),
            resource_path('views/admin/owner-withdrawals/*.blade.php'),
            resource_path('views/admin/owner-settings/*.blade.php'),
        ] as $pattern) {
            foreach (glob($pattern) as $path) {
                $this->assertStringContainsString("@extends('layouts.admin')", file_get_contents($path), $path);
            }
        }
    }

    public function test_owner_index_queries_are_paginated_and_preserve_filters(): void
    {
        $user = file_get_contents(app_path('Http/Controllers/User/PropertyOwnerController.php'));
        $admin = file_get_contents(app_path('Http/Controllers/Admin/OwnerMarketplaceController.php'));

        $this->assertGreaterThanOrEqual(3, substr_count($user, 'paginate(10)->withQueryString()'));
        $this->assertGreaterThanOrEqual(2, substr_count($admin, 'paginate(10)->withQueryString()'));
    }

    public function test_owner_admin_views_use_azari_theme_components(): void
    {
        foreach ([
            resource_path('views/admin/owner-listings/index.blade.php'),
            resource_path('views/admin/owner-listings/show.blade.php'),
            resource_path('views/admin/owner-withdrawals/index.blade.php'),
            resource_path('views/admin/owner-withdrawals/show.blade.php'),
            resource_path('views/admin/owner-settings/edit.blade.php'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertStringContainsString('az-admin-', $source, $path);
            $this->assertStringNotContainsString('class="admin-card"', $source, $path);
            $this->assertStringNotContainsString('class="admin-page-header"', $source, $path);
        }
    }

    public function test_every_blade_template_compiles(): void
    {
        Artisan::call('view:clear');
        $this->assertSame(0, Artisan::call('view:cache'), Artisan::output());
        Artisan::call('view:clear');
    }
}

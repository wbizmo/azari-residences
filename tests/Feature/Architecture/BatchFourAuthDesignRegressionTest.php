<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

final class BatchFourAuthDesignRegressionTest extends TestCase
{
    public function test_all_entrance_and_recovery_views_share_real_logo_and_branded_layout(): void
    {
        $login = $this->get(route('login'))->assertOk();
        $registration = $this->get(route('register'))->assertOk();
        $recovery = $this->get(route('password.request'))->assertOk();

        foreach ([$login, $registration, $recovery] as $response) {
            $response->assertSee('resavar-auth-unified', false)
                ->assertSee('images/logo-dark.png', false)
                ->assertSee('azari-guest-auth-suite.png', false)
                ->assertSee('Skip to', false);
        }
        $login->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="company_website"', false)
            ->assertSee('name="contact_fax"', false);

        $registration->assertSee('name="_auth_form_token"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="company_website"', false);
        $recovery->assertSee('name="_auth_form_token"', false);
    }

    public function test_staff_login_uses_same_shell_without_guest_registration_fields(): void
    {
        $response = $this->get(route('azari.admin.login'))->assertOk();
        $response->assertSee('resavar-auth-unified', false)
            ->assertSee('images/logo-dark.png', false)
            ->assertSee('azari-admin-reception.png', false)
            ->assertSee('name="login"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="company_website"', false)
            ->assertDontSee('name="_auth_form_token"', false);
    }

    public function test_single_auth_css_entrypoint_and_customer_registration_guard_remain(): void
    {
        $entry = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString("@import './reserva/auth-foundation.css';", $entry);
        $this->assertStringContainsString("@import './reserva/ui-foundation.css';", $entry);
        $css = file_get_contents(resource_path('css/reserva/auth-foundation.css'));
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('max-width: 350px', $css);
        $this->assertStringContainsString(':focus-visible', $css);

        foreach (['auth/login.blade.php', 'auth/register.blade.php', 'admin/auth/login.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/'.$view));
            $this->assertStringNotContainsString('<style>', $source);
            $this->assertStringContainsString('resavar-auth-body', $source);
            $this->assertStringContainsString('company_website', $source);
            $this->assertStringContainsString('contact_fax', $source);
        }

        $compatible = file_get_contents(resource_path('views/auth/customer-register.blade.php'));
        $this->assertStringContainsString("include('auth.register'", $compatible);
        $this->assertStringContainsString("formToken('register')", $compatible);
    }

    public function test_actual_brand_files_and_accessible_semantic_ui_components_are_used(): void
    {
        $this->assertFileExists(public_path('images/logo-light.png'));
        $this->assertFileExists(public_path('images/logo-dark.png'));
        $this->assertFileExists(public_path('images/azari-guest-auth-suite.png'));

        $brand = file_get_contents(resource_path('views/components/brand-logo.blade.php'));
        $this->assertStringContainsString('images/logo-dark.png', $brand);
        $this->assertStringContainsString('images/logo-light.png', $brand);

        $navigation = file_get_contents(resource_path('views/public/partials/navigation.blade.php'));
        $this->assertStringContainsString('images/logo-dark.png', $navigation);
        $this->assertStringContainsString('images/logo-light.png', $navigation);

        $input = file_get_contents(resource_path('views/components/ui/input.blade.php'));
        $this->assertStringContainsString('aria-describedby', $input);
        $this->assertStringContainsString('aria-invalid', $input);
        $this->assertStringContainsString('role="alert"', $input);
    }
}

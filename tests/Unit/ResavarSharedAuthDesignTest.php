<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ResavarSharedAuthDesignTest extends TestCase
{
    public function test_login_and_registration_reuse_the_existing_brand_asset_component(): void
    {
        foreach (['login', 'register'] as $page) {
            $view = file_get_contents(dirname(__DIR__, 2)."/resources/views/auth/{$page}.blade.php");

            self::assertIsString($view);
            self::assertStringContainsString('class="auth resavar-auth-unified"', $view);
            self::assertStringContainsString('<x-brand-logo variant="hero" dark />', $view);
            self::assertStringContainsString('resavar-auth-visual-copy', $view);
            self::assertStringContainsString('azari-guest-auth-suite.png', $view);
            self::assertStringContainsString('@csrf', $view);
            self::assertStringContainsString("route('{$page}')", $view);
        }
    }

    public function test_the_shared_stylesheet_is_loaded_with_the_existing_design_system(): void
    {
        $entry = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
        $design = file_get_contents(dirname(__DIR__, 2).'/resources/css/reserva/auth-refocus.css');
        $brand = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/brand-logo.blade.php');

        self::assertStringContainsString("@import './reserva/auth-refocus.css';", $entry);
        self::assertStringContainsString('.resavar-auth-unified', $design);
        self::assertStringContainsString('images/logo-dark.png', $brand);
        self::assertStringContainsString('images/logo-light.png', $brand);
    }
}

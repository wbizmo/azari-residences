<?php

namespace Tests\Feature\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class ReservaDesignSystemRegressionTest extends TestCase
{
    public function test_app_css_is_a_thin_entrypoint_with_reserva_loaded_after_legacy_rules(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertLessThan(30, substr_count($css, "\n") + 1);
        $this->assertStringContainsString("@import './legacy/app-legacy.css';", $css);
        $this->assertStringContainsString("@import './reserva/reserva.css';", $css);
        $this->assertLessThan(
            strpos($css, "@import './reserva/reserva.css';"),
            strpos($css, "@import './legacy/app-legacy.css';")
        );
    }

    public function test_reserva_tokens_do_not_collapse_light_and_dark_semantic_roles_to_navy(): void
    {
        $tokens = file_get_contents(resource_path('css/reserva/tokens.css'));

        $this->assertStringContainsString('--reserva-navy: #052058;', $tokens);
        $this->assertStringContainsString('--reserva-white: #FFFFFF;', $tokens);
        $this->assertStringContainsString('--azari-ivory: var(--reserva-white);', $tokens);
        $this->assertStringContainsString('--az-user-green-100: var(--reserva-surface-soft);', $tokens);
        $this->assertStringNotContainsString('--azari-ivory: #052058;', $tokens);
        $this->assertStringNotContainsString('--az-user-green-100: #052058;', $tokens);
        $this->assertStringNotContainsString('--az-admin-muted: #052058;', $tokens);
    }

    public function test_authenticated_contrast_rules_explicitly_define_inverse_foregrounds(): void
    {
        $css = file_get_contents(resource_path('css/reserva/authenticated.css'));

        foreach ([
            '.az-admin-sidebar',
            '.az-dashboard-hero',
            '.az-user-sidebar',
            '.az-user-welcome',
            '.az-user-timezone-icon',
            '.az-user-summary-icon',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }

        $this->assertStringContainsString('color: var(--reserva-white) !important;', $css);
        $this->assertStringContainsString('color: var(--reserva-navy) !important;', $css);
    }

    public function test_customer_facing_templates_do_not_contain_the_retired_resavar_name(): void
    {
        $files = $this->bladeFiles(resource_path('views'));

        foreach ([
            app_path('Http/Controllers/PublicSite'),
            app_path('Mail'),
            app_path('Notifications'),
        ] as $path) {
            if (is_dir($path)) {
                $files = array_merge($files, $this->phpFiles($path));
            }
        }

        $files[] = new SplFileInfo(config_path('app.php'));
        $files[] = new SplFileInfo(public_path('manifest.webmanifest'));

        $offenders = [];

        foreach ($files as $file) {
            $contents = file_get_contents($file->getPathname());

            if (preg_match('/\b(?:Resavar|RESAVAR)\b/', $contents) === 1) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Retired Resavar branding remains in: '.implode(', ', $offenders)
        );
    }

    private function phpFiles(string $path): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function bladeFiles(string $path): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }
}

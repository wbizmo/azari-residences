<?php

namespace Tests\Feature\Frontend;

use Tests\TestCase;

class ResavarToggleControlsTest extends TestCase
{
    public function test_shared_scripts_do_not_inject_a_second_checkbox_track(): void
    {
        foreach ([
            'resources/js/azari-production-hotfix.js',
            'resources/js/azari-production-ui.js',
        ] as $path) {
            $script = file_get_contents(base_path($path));

            $this->assertIsString($script);
            $this->assertStringNotContainsString("insertAdjacentElement('afterend'", $script);
            $this->assertStringNotContainsString("document.createElement('span')", $script);
        }
    }

    public function test_switch_contrast_styles_are_loaded_for_every_area(): void
    {
        $imports = file_get_contents(resource_path('css/reserva/reserva.css'));
        $switches = file_get_contents(resource_path('css/reserva/toggles.css'));

        $this->assertIsString($imports);
        $this->assertIsString($switches);
        $this->assertStringContainsString("@import './toggles.css';", $imports);
        $this->assertStringContainsString('--resavar-switch-navy: #052058;', $switches);
        $this->assertStringContainsString('--resavar-switch-white: #FFFFFF;', $switches);
        $this->assertStringContainsString('background-color: var(--resavar-switch-white);', $switches);
        $this->assertStringContainsString('background-color: var(--resavar-switch-navy);', $switches);
    }

    public function test_legacy_admin_css_only_draws_the_authored_track(): void
    {
        $styles = file_get_contents(public_path('css/azari-admin-extension.css'));

        $this->assertIsString($styles);
        $this->assertStringContainsString('.az-toggle > .az-toggle-track', $styles);
        $this->assertStringNotContainsString('.az-toggle span{', $styles);
    }
}

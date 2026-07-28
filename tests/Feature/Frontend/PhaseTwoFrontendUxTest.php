<?php

namespace Tests\Feature\Frontend;

use Tests\TestCase;

class PhaseTwoFrontendUxTest extends TestCase
{
    public function test_frontend_ux_module_is_imported_by_the_main_entrypoint(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            "import './azari-frontend-ux.js';",
            $app
        );
    }

    public function test_frontend_ux_styles_are_imported_by_the_main_stylesheet(): void
    {
        $app = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(
            "@import './azari-frontend-ux.css';",
            $app
        );
    }

    public function test_frontend_module_covers_submission_confirmation_async_and_toggle_states(): void
    {
        $module = file_get_contents(resource_path('js/azari-frontend-ux.js'));

        $this->assertStringContainsString('aria-busy', $module);
        $this->assertStringContainsString('dataset.confirm', $module);
        $this->assertStringContainsString('requestConfirmation', $module);
        $this->assertStringNotContainsString('window.confirm(', $module);
        $this->assertStringNotContainsString('window.alert(', $module);
        $this->assertStringNotContainsString('window.prompt(', $module);
        $this->assertStringContainsString('data-async-form', $module);
        $this->assertStringContainsString('data-toggle-target', $module);
        $this->assertStringContainsString('data-copy', $module);
        $this->assertStringContainsString('azari:form-success', $module);
        $this->assertStringContainsString('azari:form-error', $module);
    }

    public function test_frontend_styles_cover_loading_error_and_empty_states(): void
    {
        $styles = file_get_contents(resource_path('css/azari-frontend-ux.css'));

        $this->assertStringContainsString('form.is-submitting', $styles);
        $this->assertStringContainsString('[data-validation-error]', $styles);
        $this->assertStringContainsString('[data-empty-state]', $styles);
        $this->assertStringContainsString('[data-loading-state]', $styles);
        $this->assertStringContainsString('prefers-reduced-motion', $styles);
        $this->assertStringContainsString('.azari-confirmation-layer', $styles);
        $this->assertStringContainsString('.azari-confirmation-card', $styles);
    }

    public function test_frontend_audit_command_passes(): void
    {
        $this->artisan('azari:frontend-audit', ['--strict' => true])
            ->expectsOutputToContain('Phase 2 frontend integration audit passed.')
            ->assertSuccessful();
    }
}

<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PhaseEightArchitectureTest extends TestCase
{
    public function test_final_production_audit_passes(): void
    {
        $this->artisan('azari:final-production-audit', ['--strict' => true])
            ->expectsOutputToContain('Phase 8 final production hardening audit passed.')
            ->assertSuccessful();
    }

    public function test_material_symbols_are_local_and_visually_contained(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString("@font-face {", $css);
        $this->assertStringContainsString("font-family: 'Material Symbols Outlined';", $css);
        $this->assertStringContainsString("font-display: block;", $css);
        $this->assertStringContainsString("src: url('/fonts/material-symbols-outlined.woff2') format('woff2');", $css);
        $this->assertFileExists(public_path('fonts/material-symbols-outlined.woff2'));
        $this->assertStringContainsString("font-feature-settings: 'liga';", $css);
        $this->assertStringContainsString('overflow: hidden;', $css);
        $this->assertStringContainsString('width: 1em;', $css);
    }

    public function test_all_vite_document_shells_preload_material_symbols(): void
    {
        $shells = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->filter(function ($file): bool {
                $content = File::get($file->getPathname());

                return str_contains($content, '<head') && str_contains($content, '@vite(');
            });

        $this->assertNotEmpty($shells);

        foreach ($shells as $shell) {
            $this->assertStringContainsString(
                "@include('partials.material-symbols-preload')",
                File::get($shell->getPathname()),
                str_replace(base_path().DIRECTORY_SEPARATOR, '', $shell->getPathname())
                    .' does not include the Material Symbols preload.'
            );
        }
    }
}

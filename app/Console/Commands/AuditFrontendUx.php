<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditFrontendUx extends Command
{
    protected $signature = 'azari:frontend-audit {--strict : Fail when required Phase 2 integration is missing}';

    protected $description = 'Audit Azari frontend forms, controls and Phase 2 UX integration.';

    public function handle(): int
    {
        $issues = [];
        $warnings = [];

        $appJs = $this->read('resources/js/app.js', $issues);
        $appCss = $this->read('resources/css/app.css', $issues);
        $uxJs = $this->read('resources/js/azari-frontend-ux.js', $issues);
        $uxCss = $this->read('resources/css/azari-frontend-ux.css', $issues);

        if (! str_contains($appJs, "import './azari-frontend-ux.js';")) {
            $issues[] = 'resources/js/app.js does not import the Phase 2 UX module.';
        }

        if (! str_contains($appCss, "@import './azari-frontend-ux.css';")) {
            $issues[] = 'resources/css/app.css does not import the Phase 2 UX stylesheet.';
        }

        foreach (['data-async-form', 'aria-busy', 'dataset.confirm', 'requestConfirmation', 'azari:form-success'] as $marker) {
            if (! str_contains($uxJs, $marker)) {
                $issues[] = "Frontend UX JavaScript is missing required marker [{$marker}].";
            }
        }

        foreach (['window.alert(', 'window.confirm(', 'window.prompt('] as $forbiddenDialog) {
            if (str_contains($uxJs, $forbiddenDialog)) {
                $issues[] = "Frontend UX JavaScript contains forbidden browser dialog [{$forbiddenDialog}].";
            }
        }

        foreach (['is-submitting', 'data-validation-error', 'data-empty-state', 'data-loading-state'] as $marker) {
            if (! str_contains($uxCss, $marker)) {
                $issues[] = "Frontend UX stylesheet is missing required marker [{$marker}].";
            }
        }

        $bladeFiles = File::allFiles(resource_path('views'));
        $formCount = 0;
        $buttonCount = 0;

        foreach ($bladeFiles as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = $file->getContents();
            $relative = $file->getRelativePathname();
            $formCount += preg_match_all('/<form\b/i', $content);
            $buttonCount += preg_match_all('/<button\b/i', $content);

            if (preg_match('/<button\b(?![^>]*\btype=)[^>]*>/i', $content)) {
                $warnings[] = "{$relative} contains a button without an explicit type.";
            }

            if (preg_match('/\bonclick\s*=/i', $content)) {
                $warnings[] = "{$relative} contains inline onclick JavaScript.";
            }
        }

        $this->line("Blade templates: ".count($bladeFiles));
        $this->line("Forms found: {$formCount}");
        $this->line("Buttons found: {$buttonCount}");

        foreach (array_unique($warnings) as $warning) {
            $this->warn($warning);
        }

        if ($issues !== []) {
            foreach (array_unique($issues) as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        $this->info('Phase 2 frontend integration audit passed.');

        return self::SUCCESS;
    }

    private function read(string $path, array &$issues): string
    {
        $absolute = base_path($path);

        if (! File::exists($absolute)) {
            $issues[] = "Required frontend file is missing: {$path}";

            return '';
        }

        return File::get($absolute);
    }
}

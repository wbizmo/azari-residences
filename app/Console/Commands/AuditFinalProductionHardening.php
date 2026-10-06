<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class AuditFinalProductionHardening extends Command
{
    protected $signature = 'azari:final-production-audit
        {--strict : Fail for confirmed Phase 8 regressions}';

    protected $description = 'Audit final repository cleanup, Material Symbols loading and production readiness.';

    public function handle(): int
    {
        try {
            return $this->runAudit();
        } catch (Throwable $exception) {
            $this->error('Phase 8 audit exception: '.$exception->getMessage());
            $this->line($exception->getFile().':'.$exception->getLine());

            return self::FAILURE;
        }
    }

    private function runAudit(): int
    {
        $issues = [];
        $cssPath = resource_path('css/app.css');
        $preloadPartial = resource_path('views/partials/material-symbols-preload.blade.php');
        $fontPath = public_path('fonts/material-symbols-outlined.woff2');

        if (! File::exists($cssPath)) {
            $issues[] = 'resources/css/app.css is missing.';
        } else {
            $css = File::get($cssPath);
            $legacyCssPath = resource_path('css/legacy/app-legacy.css');

            if (File::exists($legacyCssPath)) {
                $css .= "
".File::get($legacyCssPath);
            }

            foreach ([
                "@font-face {",
                "font-family: 'Material Symbols Outlined';",
                "font-display: block;",
                "src: url('/fonts/material-symbols-outlined.woff2') format('woff2');",
                "font-feature-settings: 'liga';",
                'overflow: hidden;',
                'width: 1em;',
                'contain: inline-size layout paint;',
            ] as $required) {
                if (! str_contains($css, $required)) {
                    $issues[] = "Material Symbols hardening rule missing: {$required}";
                }
            }

            if (preg_match('/@font-face[\s\S]*?@import/i', $css)) {
                $issues[] = 'A CSS @import appears after @font-face; imports must remain first.';
            }

            if (str_contains($css, "@import 'material-symbols/outlined.css';") ||
                str_contains($css, '@import "material-symbols/outlined.css";')) {
                $issues[] = 'The old package Material Symbols CSS import remains.';
            }
        }

        if (! File::exists($fontPath)) {
            $issues[] = 'Static Material Symbols font is missing from public/fonts.';
        } elseif (File::size($fontPath) < 1000) {
            $issues[] = 'Static Material Symbols font appears empty or invalid.';
        }

        if (! File::exists($preloadPartial)) {
            $issues[] = 'Material Symbols preload partial is missing.';
        } else {
            $partial = File::get($preloadPartial);

            foreach ([
                "asset('fonts/material-symbols-outlined.woff2')",
                'rel="preload"',
                'as="font"',
                'type="font/woff2"',
                'crossorigin',
            ] as $required) {
                if (! str_contains($partial, $required)) {
                    $issues[] = "Material Symbols preload implementation missing: {$required}";
                }
            }
        }

        $shells = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->filter(function ($file): bool {
                $content = File::get($file->getPathname());

                return str_contains($content, '<head') && str_contains($content, '@vite(');
            })
            ->values();

        if ($shells->isEmpty()) {
            $issues[] = 'No Vite-powered Blade document shells were found.';
        }

        foreach ($shells as $shell) {
            $content = File::get($shell->getPathname());

            if (! str_contains($content, "@include('partials.material-symbols-preload')")) {
                $issues[] = $this->relative($shell->getPathname())
                    .' does not preload the local Material Symbols font.';
            }
        }

        foreach ($this->repositoryFiles() as $relative => $absolute) {
            $basename = basename($relative);

            foreach ($this->explicitBackupPatterns() as $pattern) {
                if (preg_match($pattern, $basename)) {
                    $issues[] = 'Backup or editor residue remains: '.$relative;
                    continue 2;
                }
            }

            foreach ($this->timestampPatterns() as $pattern) {
                if (! preg_match($pattern, $basename, $matches)) {
                    continue;
                }

                $directory = dirname($relative);
                $directory = $directory === '.' ? '' : $directory.DIRECTORY_SEPARATOR;
                $original = $directory.$matches['stem'].($matches['suffix'] ?? '');

                $files = $this->repositoryFiles();

                if (isset($files[$original]) &&
                    strtolower(pathinfo($original, PATHINFO_EXTENSION)) ===
                    strtolower(pathinfo($relative, PATHINFO_EXTENSION))) {
                    $issues[] = "Timestamped backup remains: {$relative} (original: {$original})";
                    continue 2;
                }
            }
        }

        if ($issues !== []) {
            foreach (array_values(array_unique($issues)) as $issue) {
                $this->error($issue);
            }

            $this->error('Phase 8 final production audit found '.count(array_unique($issues)).' issue(s).');

            return self::FAILURE;
        }

        $this->info('Phase 8 final production hardening audit passed.');

        return self::SUCCESS;
    }

    /** @return array<string, string> */
    private function repositoryFiles(): array
    {
        static $files;

        if (is_array($files)) {
            return $files;
        }

        $files = [];
        $roots = [
            app_path(),
            base_path('bootstrap'),
            base_path('config'),
            base_path('database'),
            public_path(),
            resource_path(),
            base_path('routes'),
            base_path('tests'),
        ];

        foreach ($roots as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                $absolute = $file->getPathname();
                $relative = $this->relative($absolute);

                if (str_starts_with($relative, 'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR) ||
                    str_starts_with($relative, 'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $files[$relative] = $absolute;
            }
        }

        foreach (File::files(base_path()) as $file) {
            $files[$file->getFilename()] = $file->getPathname();
        }

        ksort($files);

        return $files;
    }

    /** @return array<int, string> */
    private function explicitBackupPatterns(): array
    {
        return [
            '/\.bak(?:\d+)?$/i',
            '/\.backup(?:\..+)?$/i',
            '/\.orig$/i',
            '/\.rej$/i',
            '/\.save(?:\..+)?$/i',
            '/\.sw[op]$/i',
            '/\.tmp$/i',
            '/~$/',
            '/\.old(?:\..+)?$/i',
            '/\.copy(?:\..+)?$/i',
        ];
    }

    /** @return array<int, string> */
    private function timestampPatterns(): array
    {
        return [
            '/^(?<stem>.+?)[._-](?<stamp>(?:19|20)\d{2}[-_.]?(?:0[1-9]|1[0-2])[-_.]?(?:0[1-9]|[12]\d|3[01]))(?<suffix>(?:\.[^.\/]+)+)$/i',
            '/^(?<stem>.+?(?:\.[^.\/]+)+)[._-](?<stamp>(?:19|20)\d{2}[-_.]?(?:0[1-9]|1[0-2])[-_.]?(?:0[1-9]|[12]\d|3[01]))$/i',
            '/^(?<stem>.+?)[._-](?<stamp>(?:19|20)\d{2}[-_.]?(?:0[1-9]|1[0-2])[-_.]?(?:0[1-9]|[12]\d|3[01])(?:[-_.]?(?:[01]\d|2[0-3])[-_.]?[0-5]\d(?:[-_.]?[0-5]\d)?)?)(?<suffix>(?:\.[^.\/]+)+)$/i',
        ];
    }

    private function relative(string $path): string
    {
        return str_replace(
            [base_path().DIRECTORY_SEPARATOR, '\\'],
            ['', DIRECTORY_SEPARATOR],
            $path
        );
    }
}

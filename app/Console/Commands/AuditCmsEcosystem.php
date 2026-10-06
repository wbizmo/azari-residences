<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditCmsEcosystem extends Command
{
    protected $signature = 'azari:cms-ecosystem-audit
        {--strict : Fail for confirmed CMS, admin or user-ecosystem regressions}';

    protected $description = 'Audit the existing Resarva CMS, admin and user ecosystem without rebuilding completed modules.';

    public function handle(): int
    {
        $issues = [];
        $warnings = [];

        $controllers = $this->phpFiles(app_path('Http/Controllers'));
        $models = $this->phpFiles(app_path('Models'));
        $views = $this->bladeFiles(resource_path('views'));

        $requiredViewAreas = [
            'admin' => resource_path('views/admin'),
            'user' => resource_path('views/user'),
        ];

        foreach ($requiredViewAreas as $label => $path) {
            if (! File::isDirectory($path)) {
                $issues[] = "Missing {$label} view area.";
            }
        }

        $ecosystemTerms = [
            'cms' => ['cms', 'content', 'promotion'],
            'settings' => ['setting'],
            'concierge' => ['concierge', 'service request'],
            'support' => ['support', 'ticket'],
            'notifications' => ['notification'],
            'exports' => ['export', 'invoice', 'receipt', 'download'],
        ];

        $haystack = strtolower(implode("\n", array_map(
            static fn (string $file): string => $file."\n".File::get($file),
            array_merge($controllers, $models, $views, $this->phpFiles(base_path('routes')))
        )));

        foreach ($ecosystemTerms as $module => $needles) {
            $found = false;

            foreach ($needles as $needle) {
                if (str_contains($haystack, strtolower($needle))) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $issues[] = "No repository implementation found for the {$module} ecosystem.";
            }
        }

        foreach ($controllers as $file) {
            $content = File::get($file);
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);

            if (preg_match('/->get\s*\(\s*\)\s*;/', $content) &&
                preg_match('/function\s+(index|history|logs|report|notifications|tickets|bookings|payments)\b/i', $content) &&
                ! str_contains($content, 'paginate(') &&
                ! str_contains($content, 'simplePaginate(')) {
                $warnings[] = "{$relative} may contain an unpaginated index-style query.";
            }

            if (preg_match('/return\s+(?:response\(\)->)?(?:download|streamDownload)\s*\(/', $content) &&
                ! preg_match('/authorize\s*\(|Gate::|can\s*\(|abort_unless\s*\(|abort_if\s*\(/', $content)) {
                $warnings[] = "{$relative} contains a download response; verify ownership or permission enforcement remains server-side.";
            }
        }

        $this->line('Controllers scanned: '.count($controllers));
        $this->line('Models scanned: '.count($models));
        $this->line('Blade templates scanned: '.count($views));

        foreach (array_unique($warnings) as $warning) {
            $this->warn($warning);
        }

        if ($issues !== []) {
            foreach (array_unique($issues) as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        $this->info('Phase 6 CMS, admin and user ecosystem audit passed.');

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function phpFiles(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::allFiles($directory))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->map(fn ($file) => $file->getPathname())
            ->sort()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function bladeFiles(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::allFiles($directory))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn ($file) => $file->getPathname())
            ->sort()
            ->values()
            ->all();
    }
}

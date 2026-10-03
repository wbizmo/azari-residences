<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class AuditControllerRequests extends Command
{
    protected $signature = 'azari:controller-audit
        {--strict : Fail for unsafe request payload access and missing Phase 3A infrastructure}';

    protected $description = 'Audit Reserva controllers and Form Requests for request-validation and response-boundary risks.';

    public function handle(): int
    {
        $issues = [];
        $warnings = [];

        $baseRequest = app_path('Http/Requests/AzariFormRequest.php');

        if (! File::exists($baseRequest)) {
            $issues[] = 'The canonical AzariFormRequest class is missing.';
        }

        $controllers = $this->phpFiles(app_path('Http/Controllers'));
        $requests = $this->phpFiles(app_path('Http/Requests'));

        $directValidation = 0;
        $formRequests = 0;

        foreach ($controllers as $file) {
            $content = File::get($file);
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);

            if (preg_match('/\$_(?:GET|POST|REQUEST|FILES)\b/', $content)) {
                $issues[] = "{$relative} reads PHP request superglobals directly.";
            }

            if (preg_match('/\bextract\s*\(/', $content)) {
                $issues[] = "{$relative} uses extract(), which obscures request boundaries.";
            }

            if (preg_match('/\$request->all\s*\(\s*\)/', $content)) {
                $issues[] = "{$relative} uses unfiltered request->all().";
            }

            $directValidation += preg_match_all('/\$request->validate\s*\(|Validator::make\s*\(/', $content);

            if (preg_match('/response\(\)->json\s*\([^;]*->getMessage\s*\(/s', $content)) {
                $warnings[] = "{$relative} may expose a raw exception message in JSON.";
            }

            if (preg_match('/catch\s*\(\s*\\\\?Throwable\b[^)]*\)\s*\{\s*\}/s', $content)) {
                $warnings[] = "{$relative} contains an empty Throwable catch block.";
            }
        }

        foreach ($requests as $file) {
            if (str_ends_with($file, 'AzariFormRequest.php')) {
                continue;
            }

            $content = File::get($file);
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);

            if (preg_match('/class\s+\w+\s+extends\s+AzariFormRequest\b/', $content)) {
                $formRequests++;

                continue;
            }

            if (preg_match('/class\s+\w+\s+extends\s+FormRequest\b/', $content)) {
                $issues[] = "{$relative} bypasses the canonical AzariFormRequest base.";
            }
        }

        $this->line('Controllers scanned: '.count($controllers));
        $this->line('Request classes scanned: '.count($requests));
        $this->line("Canonical Form Requests: {$formRequests}");
        $this->line("Direct validation sites: {$directValidation}");

        if ($directValidation > 0) {
            $warnings[] = "{$directValidation} direct controller validation site(s) remain for incremental migration; no behavior was rewritten blindly.";
        }

        foreach (array_unique($warnings) as $warning) {
            $this->warn($warning);
        }

        if ($issues !== []) {
            foreach (array_unique($issues) as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        $this->info('Phase 3A controller and request audit passed.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
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
}

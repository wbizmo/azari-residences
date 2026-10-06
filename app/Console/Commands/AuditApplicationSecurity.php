<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditApplicationSecurity extends Command
{
    protected $signature = 'azari:security-audit
        {--strict : Fail for confirmed application-security regressions}';

    protected $description = 'Audit Resarva XSS, CSRF, uploads, downloads, headers, cookies, rate limits and audit logging.';

    public function handle(): int
    {
        $issues = [];
        $warnings = [];

        $middleware = app_path('Http/Middleware/SecurityHeaders.php');
        $bootstrap = base_path('bootstrap/app.php');

        if (! File::exists($middleware)) {
            $issues[] = 'SecurityHeaders middleware is missing.';
        } else {
            $content = File::get($middleware);

            foreach ([
                'X-Content-Type-Options',
                'X-Frame-Options',
                'Referrer-Policy',
                'Permissions-Policy',
                'Strict-Transport-Security',
            ] as $header) {
                if (! str_contains($content, $header)) {
                    $issues[] = "Security response header missing: {$header}.";
                }
            }
        }

        if (! File::exists($bootstrap) ||
            ! str_contains(File::get($bootstrap), 'SecurityHeaders::class')) {
            $issues[] = 'SecurityHeaders is not registered globally.';
        }

        foreach ($this->files([app_path(), base_path('routes'), resource_path('views')]) as $file) {
            $content = File::get($file);
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);

            if (str_ends_with($file, '.blade.php')) {
                if (preg_match('/\{!!\s*(?!\$errors\b|old\()[\s\S]*?!!\}/', $content)) {
                    $warnings[] = "{$relative} contains raw Blade output; verify the source is trusted or sanitized.";
                }

                if (preg_match('/<form\b/i', $content) &&
                    preg_match('/method\s*=\s*["\']post["\']/i', $content) &&
                    ! str_contains($content, '@csrf')) {
                    $issues[] = "{$relative} contains a POST form without an explicit @csrf token.";
                }
            }

            if (str_ends_with($file, '.php')) {
                if (preg_match('/\bfile_get_contents\s*\(\s*\$|Http::(?:get|post|send)\s*\(\s*\$/', $content)) {
                    $warnings[] = "{$relative} may perform an outbound request using a dynamic URL; verify SSRF allowlisting.";
                }

                if (preg_match('/\b(store|storeAs|putFile|putFileAs)\s*\(/', $content) &&
                    ! preg_match('/\b(mimes|mimetypes|extensions|image|max):/', $content) &&
                    ! str_contains($content, 'validated(')) {
                    $warnings[] = "{$relative} stores uploads; verify validated MIME, extension and size constraints.";
                }

                if (preg_match('/response\(\)->(?:download|streamDownload)\s*\(|Storage::download\s*\(/', $content) &&
                    ! preg_match('/authorize\s*\(|Gate::|can\s*\(|abort_unless\s*\(|abort_if\s*\(/', $content)) {
                    $warnings[] = "{$relative} returns a file download; verify ownership or permission enforcement.";
                }
            }
        }

        $routes = collect($this->files([base_path('routes')]))
            ->map(fn (string $file): string => File::get($file))
            ->implode("\n");

        if (! str_contains($routes, 'throttle:') && ! str_contains($routes, 'RateLimiter')) {
            $warnings[] = 'No explicit route-level throttle declaration was found; verify application-level rate limiting.';
        }

        $config = File::exists(config_path('session.php'))
            ? File::get(config_path('session.php'))
            : '';

        foreach (["'secure'", "'http_only'", "'same_site'"] as $cookieSetting) {
            if (! str_contains($config, $cookieSetting)) {
                $warnings[] = "Session configuration does not visibly declare {$cookieSetting}.";
            }
        }

        $auditSignal = strtolower(implode("\n", array_map(
            static fn (string $file): string => $file."\n".File::get($file),
            $this->files([app_path()])
        )));

        if (! str_contains($auditSignal, 'audit') && ! str_contains($auditSignal, 'activity')) {
            $issues[] = 'No audit/activity logging implementation was detected.';
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

        $this->info('Phase 7 application security audit passed.');

        return self::SUCCESS;
    }

    /** @param array<int, string> $directories
     *  @return array<int, string>
     */
    private function files(array $directories): array
    {
        return collect($directories)
            ->filter(fn (string $directory): bool => File::isDirectory($directory))
            ->flatMap(fn (string $directory) => File::allFiles($directory))
            ->filter(fn ($file): bool =>
                $file->getExtension() === 'php' ||
                str_ends_with($file->getFilename(), '.blade.php')
            )
            ->map(fn ($file): string => $file->getPathname())
            ->sort()
            ->values()
            ->all();
    }
}

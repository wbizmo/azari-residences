<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResarvaReleaseGate extends Command
{
    protected $signature = 'azari:release-gate {--ci : Run only deterministic lightweight checks suitable for CI}';
    protected $description = 'Validate deployment prerequisites without running the Laravel or browser test suites.';

    public function handle(): int
    {
        $checks = [
            'application key present' => filled(config('app.key')),
            'database reachable' => $this->safe(fn () => DB::select('select 1')),
            'critical booking schema present' => Schema::hasTable('bookings') && Schema::hasTable('payments') && Schema::hasTable('inventory_dates'),
            'channel schema present' => Schema::hasTable('channel_connections') && Schema::hasTable('channel_reservations'),
            'frontend build manifest present' => is_file(public_path('build/manifest.json')),
            'service worker is static-only' => $this->serviceWorkerSafe(),
            'Core Web Vitals budgets configured' => $this->performanceBudgetsSafe(),
            'filtered search is noindex' => $this->searchIndexingSafe(),
            'responsive image pipeline configured' => $this->responsiveImagePipelineSafe(),
            'public views avoid hard-coded currency symbols' => $this->currencyViewsSafe(),
        ];

        if (! $this->option('ci')) {
            $checks['sitemap generation'] = $this->safe(fn () => throw_if(Artisan::call('azari:sitemap') !== 0, new \RuntimeException('sitemap failed')));
            $checks['sitemap present'] = is_file(public_path('sitemap.xml')) && filesize(public_path('sitemap.xml')) > 100;
            $checks['robots present'] = is_file(public_path('robots.txt')) && str_contains((string) file_get_contents(public_path('robots.txt')), 'Sitemap:');
        }

        $failed = false;
        foreach ($checks as $label => $ok) { $this->line(($ok ? '[PASS] ' : '[FAIL] ').$label); $failed = $failed || ! $ok; }
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function performanceBudgetsSafe(): bool
    {
        return (int) config('reserva.performance.lcp_ms', 0) > 0
            && (int) config('reserva.performance.lcp_ms') <= 2500
            && (int) config('reserva.performance.inp_ms', 0) > 0
            && (int) config('reserva.performance.inp_ms') <= 200
            && (float) config('reserva.performance.cls', 1) <= 0.10;
    }

    private function searchIndexingSafe(): bool
    {
        foreach ([
            'public/search-results.blade.php',
            'public/search/results.blade.php',
            'public/availability-results.blade.php',
            'public/availability_results.blade.php',
        ] as $view) {
            $source = (string) @file_get_contents(resource_path('views/'.$view));
            if (! str_contains($source, "@section('robots','noindex, follow")) {
                return false;
            }
        }
        return true;
    }

    private function responsiveImagePipelineSafe(): bool
    {
        $widths = (array) config('reserva.media.responsive_widths', []);
        $formats = (array) config('reserva.media.formats', []);

        return is_file(base_path('scripts/generate-responsive-image.mjs'))
            && $widths !== []
            && collect($widths)->every(fn ($width) => is_int($width) && $width >= 160 && $width <= 2400)
            && in_array('webp', $formats, true)
            && in_array('avif', $formats, true);
    }

    private function currencyViewsSafe(): bool
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) continue;
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/[₦€£¥₹]|\$\s*\d/u', $source) === 1) return false;
        }
        return true;
    }

    private function serviceWorkerSafe(): bool
    {
        $path = public_path('service-worker.js');
        if (! is_file($path)) return false;
        $source = (string) file_get_contents($path);
        return str_contains($source, "request.mode === 'navigate'")
            && str_contains($source, "fetch(request).catch")
            && str_contains($source, "caches.match('/offline.html')")
            && str_contains($source, "url.pathname")
            && str_contains($source, "request.headers.has('Authorization')")
            && ! str_contains($source, "cache.put(request, response)");
    }

    private function safe(callable $fn): bool { try { $fn(); return true; } catch (\Throwable) { return false; } }
}

<?php

namespace App\Jobs;

use App\Support\ResponsiveImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GenerateResponsiveImageDerivatives implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public string $path) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        if (! ResponsiveImage::isManagedPath($this->path)) {
            return;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($this->path)) {
            return;
        }

        $script = base_path('scripts/generate-responsive-image.mjs');
        if (! is_file($script)) {
            throw new RuntimeException('Responsive image generator is missing.');
        }

        $widths = array_values(array_map(
            static fn ($width): int => max(160, min(2400, (int) $width)),
            (array) config('reserva.media.responsive_widths', [480, 768, 1200])
        ));

        $result = Process::timeout(90)->run([
            (string) config('reserva.media.node_binary', 'node'),
            $script,
            $disk->path($this->path),
            $disk->path(dirname($this->path).'/'.'.responsive'),
            pathinfo($this->path, PATHINFO_FILENAME),
            implode(',', $widths),
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('Responsive image generation failed: '.str($result->errorOutput() ?: $result->output())->squish()->limit(300));
        }
    }
}

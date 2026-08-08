<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Throwable;

class PublicStatusController extends Controller
{
    public function __invoke(): Response
    {
        $snapshot = $this->readSnapshot();

        $checkedAt = null;

        try {
            if (! empty($snapshot['checked_at'])) {
                $checkedAt = CarbonImmutable::parse(
                    $snapshot['checked_at']
                );
            }
        } catch (Throwable) {
            $checkedAt = null;
        }

        $staleMinutes = max(
            5,
            (int) config('azari-status.stale_minutes', 10)
        );

        $stale = $checkedAt === null
            || $checkedAt->lt(
                now()->subMinutes($staleMinutes)
            );

        $overall = $stale
            ? 'unknown'
            : ($snapshot['overall'] ?? 'unknown');

        $components = is_array(
            $snapshot['components'] ?? null
        )
            ? $snapshot['components']
            : [];

        $history = is_array(
            $snapshot['history'] ?? null
        )
            ? $snapshot['history']
            : [];

        $historyBars = $this->historyBars(
            $history
        );

        return response()
            ->view('public.status', [
                'overall' => $overall,
                'components' => $components,
                'checkedAt' => $checkedAt,
                'stale' => $stale,
                'historyBars' => $historyBars,
                'notice' => $snapshot['notice']
                    ?? 'Automated checks run every five minutes.',
            ])
            ->header(
                'Cache-Control',
                'public, max-age=60, stale-while-revalidate=120'
            )
            ->header(
                'X-Robots-Tag',
                'noindex, noarchive'
            );
    }

    public function data(): JsonResponse
    {
        $snapshot = $this->readSnapshot();

        $checkedAt = null;

        try {
            if (! empty($snapshot['checked_at'])) {
                $checkedAt = CarbonImmutable::parse(
                    $snapshot['checked_at']
                );
            }
        } catch (Throwable) {
            $checkedAt = null;
        }

        $staleMinutes = max(
            5,
            (int) config('azari-status.stale_minutes', 10)
        );

        $stale = $checkedAt === null
            || $checkedAt->lt(
                now()->subMinutes($staleMinutes)
            );

        $components = is_array(
            $snapshot['components'] ?? null
        )
            ? array_values(array_map(
                static fn (array $component): array => [
                    'key' => (string) ($component['key'] ?? ''),
                    'name' => (string) ($component['name'] ?? 'Platform service'),
                    'description' => (string) ($component['description'] ?? 'Service health.'),
                    'status' => (string) ($component['status'] ?? 'unknown'),
                ],
                $snapshot['components']
            ))
            : [];

        $overall = $stale
            ? 'unknown'
            : (string) ($snapshot['overall'] ?? 'unknown');

        $history = is_array(
            $snapshot['history'] ?? null
        )
            ? $snapshot['history']
            : [];

        return response()
            ->json([
                'ok' => true,
                'overall' => $overall,
                'components' => $components,
                'checked_at' => $checkedAt?->toIso8601String(),
                'stale' => $stale,
                'history' => $this->historyBars($history),
                'notice' => (string) (
                    $snapshot['notice']
                    ?? 'Automated checks run every five minutes.'
                ),
            ])
            ->header(
                'Cache-Control',
                'no-store, max-age=0'
            )
            ->header(
                'X-Robots-Tag',
                'noindex, noarchive'
            );
    }

    private function readSnapshot(): array
    {
        $path = (string) config(
            'azari-status.snapshot_path'
        );

        if ($path === '' || ! is_readable($path)) {
            return [];
        }

        try {
            $decoded = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            return is_array($decoded)
                ? $decoded
                : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function historyBars(array $history): array
    {
        /*
        | Six five-minute samples become one 30-minute bar.
        | 48 bars therefore represent approximately 24 hours.
        */

        $history = array_slice(
            $history,
            -288
        );

        $chunks = array_chunk(
            $history,
            6
        );

        $bars = [];

        foreach ($chunks as $chunk) {
            $worst = 'operational';

            foreach ($chunk as $sample) {
                $status = $sample['overall']
                    ?? 'unknown';

                if (
                    $this->severity($status)
                    > $this->severity($worst)
                ) {
                    $worst = $status;
                }
            }

            $bars[] = $worst;
        }

        return array_slice(
            $bars,
            -48
        );
    }

    private function severity(string $status): int
    {
        return match ($status) {
            'operational' => 0,
            'unknown' => 1,
            'degraded' => 2,
            'partial_outage' => 3,
            'major_outage' => 4,
            default => 1,
        };
    }
}

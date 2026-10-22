<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\PropertyListing;
use App\Services\Owners\SupplyReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only, provider-free weekly snapshot, retained on the private disk.
 * Dates are indicative search dates, never an inventory hold or guarantee.
 */
final class ReportAccommodationSupply extends Command
{
    protected $signature = 'resavar:report-supply-readiness
        {--check-in= : Local calendar date YYYY-MM-DD}
        {--json : Print the aggregate report as JSON}';

    protected $description = 'Generate an internal date-scoped supply readiness snapshot without altering inventory.';

    public function handle(SupplyReadinessService $service): int
    {
        $value = $this->option('check-in') ?: now()->addDays(2)->toDateString();
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', (string) $value);
        if (! $parsed || $parsed->format('Y-m-d') !== $value || $parsed->isBefore(today())) {
            $this->error('Provide a valid future or current check-in date in YYYY-MM-DD format.');
            return self::FAILURE;
        }
        $checkIn = $parsed;
        $checkOut = $checkIn->addDay();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'scope' => 'one adult, one unit, one night; indicative and not held',
            'owner_submissions' => [
                'draft' => PropertyListing::query()->where('status', 'draft')->count(),
                'awaiting_review' => PropertyListing::query()->whereIn('status',
                    ['submitted', 'under_review', 'correction_requested'])->count(),
                'approved' => PropertyListing::query()->where('status', 'approved')->count(),
            ],
            'properties' => ['total' => 0, 'draft' => 0, 'reviewed' => 0,
                'published_not_bookable' => 0, 'bookable' => 0],
            'destinations' => [],
        ];

        Property::query()->orderBy('id')->chunkById(50, function ($properties) use (
            &$report, $service, $checkIn, $checkOut
        ): void {
            $properties->load([
                'amenities', 'locationRecord', 'photoModerations', 'ownerAgreement',
                'ownerListing', 'accommodationTypes.ratePlans.paymentPolicy',
                'accommodationTypes.ratePlans.cancellationPolicy',
            ]);
            foreach ($properties as $property) {
                $result = $service->inspect($property, $checkIn, $checkOut);
                $report['properties']['total']++;
                $report['properties'][$result['state']]++;
                // No addresses, names, owner records or provider references.
                $destination = (string) ($property->locationRecord?->name ?? '');
                if ($destination !== '') {
                    $report['destinations'][$destination] ??= ['properties' => 0, 'bookable' => 0];
                    $report['destinations'][$destination]['properties']++;
                    $report['destinations'][$destination]['bookable'] += (int) $result['ready'];
                }
            }
        });

        ksort($report['destinations']);
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $path = 'reports/accommodation-supply/'.now()->format('Y-m-d').'.json';
        if (! Storage::disk('private')->put($path, $json)) {
            $this->error('Failed to preserve private supply summary.');
            return self::FAILURE;
        }
        $this->option('json') ? $this->line($json)
            : $this->info('Private supply summary saved: '.$path.' ('.$report['properties']['bookable'].' date-bookable properties)');
        return self::SUCCESS;
    }
}

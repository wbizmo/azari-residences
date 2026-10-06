<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class GeneratePublicSitemap extends Command
{
    protected $signature = 'azari:sitemap';

    protected $description =
        'Generate the public Resavar sitemap.xml and maintain robots.txt.';

    public function handle(): int
    {
        $base = rtrim((string) config('app.url'), '/');

        if ($base === '' || ! preg_match('#^https?://#i', $base)) {
            $this->error('APP_URL must be a complete HTTP/HTTPS URL.');

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Ensure CLI-generated route() URLs use this installation's APP_URL.
        |--------------------------------------------------------------------------
        */

        URL::forceRootUrl($base);

        if (str_starts_with(strtolower($base), 'https://')) {
            URL::forceScheme('https');
        }

        /*
        |--------------------------------------------------------------------------
        | Actual public, indexable routes from the Resavar application.
        |--------------------------------------------------------------------------
        |
        | Deliberately omitted:
        |
        | - authentication/account pages
        | - administrator routes
        | - payment callbacks/webhooks
        | - booking checkout/review/summary URLs
        | - search-result URLs
        | - user property-centre routes
        | - API/internal endpoints
        | - /book-now because it duplicates /availability
        | - per-property availability pages because they are booking utilities
        |
        */

        $staticRoutes = [
            ['home',                         'daily',   '1.0'],
            ['availability.index',           'daily',   '0.9'],
            ['public.apartments',            'daily',   '0.9'],
            ['public.rooms',                 'daily',   '0.9'],

            ['public.services',              'weekly',  '0.8'],
            ['public.concierge',             'weekly',  '0.8'],
            ['public.housekeeping',          'weekly',  '0.8'],
            ['public.restaurant',            'weekly',  '0.8'],
            ['public.airport-transfers',     'weekly',  '0.8'],
            ['public.local-guide',           'weekly',  '0.8'],

            ['public.about',                 'monthly', '0.8'],
            ['public.contact',               'monthly', '0.7'],
            ['public.support',               'monthly', '0.7'],
            ['bookings.verify',              'weekly',  '0.6'],

            ['public.booking-terms',         'monthly', '0.5'],
            ['public.cancellation-policy',   'monthly', '0.5'],
            ['public.privacy-policy',        'monthly', '0.5'],
            ['public.terms',                 'monthly', '0.5'],
            ['public.status',                'hourly',  '0.3'],
        ];

        $entries = [];

        foreach ($staticRoutes as [$name, $frequency, $priority]) {
            if (! Route::has($name)) {
                continue;
            }

            $entries[] = [
                'loc' => route($name),
                'lastmod' => null,
                'changefreq' => $frequency,
                'priority' => $priority,
                'images' => [],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Every active destination landing page with public inventory.
        |--------------------------------------------------------------------------
        */
        $locations = Location::query()->where('is_active', true)
            ->whereHas('properties', fn ($query) => $query->where('is_published', true))
            ->orderBy('sort_order')->orderBy('name')->get();

        foreach ($locations as $location) {
            $entries[] = [
                'loc' => route('destinations.show', $location),
                'lastmod' => $location->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'images' => [],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Every publicly published residence/property.
        |--------------------------------------------------------------------------
        |
        | The live PropertyController only requires is_published=true
        | before displaying the public property page, so sitemap inclusion
        | follows that same public visibility contract.
        |
        */

        $properties = Property::query()
            ->with('images')
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($properties as $property) {
            if (! filled($property->slug)) {
                continue;
            }

            $images = [];

            if ($property->cover_image) {
                $url = $this->imageUrl(
                    $base,
                    (string) $property->cover_image
                );

                if ($url) {
                    $images[$url] = [
                        'loc' => $url,
                        'title' => $property->name,
                        'caption' => $property->short_description
                            ?: $property->name,
                    ];
                }
            }

            foreach ($property->images->take(20) as $image) {
                $url = $this->imageUrl(
                    $base,
                    (string) $image->path
                );

                if (! $url) {
                    continue;
                }

                $images[$url] = [
                    'loc' => $url,
                    'title' => $image->title
                        ?: $image->alt_text
                        ?: $property->name,

                    'caption' => $image->caption
                        ?: $image->alt_text
                        ?: $property->short_description
                        ?: $property->name,
                ];
            }

            $entries[] = [
                'loc' => route('properties.show', $property),
                'lastmod' => $property->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.9',
                'images' => array_values($images),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | XML generation.
        |--------------------------------------------------------------------------
        */

        $xml =
            '<?xml version="1.0" encoding="UTF-8"?>'."\n".
            '<urlset '.
            'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '.
            'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'.
            "\n";

        foreach ($entries as $entry) {
            $xml .= "    <url>\n";

            $xml .= '        <loc>'.
                $this->xml($entry['loc']).
                "</loc>\n";

            if ($entry['lastmod']) {
                $xml .= '        <lastmod>'.
                    $this->xml($entry['lastmod']).
                    "</lastmod>\n";
            }

            $xml .= '        <changefreq>'.
                $this->xml($entry['changefreq']).
                "</changefreq>\n";

            $xml .= '        <priority>'.
                $this->xml($entry['priority']).
                "</priority>\n";

            foreach ($entry['images'] as $image) {
                $xml .= "        <image:image>\n";

                $xml .= '            <image:loc>'.
                    $this->xml($image['loc']).
                    "</image:loc>\n";

                if (filled($image['title'] ?? null)) {
                    $xml .= '            <image:title>'.
                        $this->xml($image['title']).
                        "</image:title>\n";
                }

                if (filled($image['caption'] ?? null)) {
                    $xml .= '            <image:caption>'.
                        $this->xml($image['caption']).
                        "</image:caption>\n";
                }

                $xml .= "        </image:image>\n";
            }

            $xml .= "    </url>\n";
        }

        $xml .= "</urlset>\n";

        /*
        |--------------------------------------------------------------------------
        | Hostinger forwards the domain root into Laravel's public directory.
        | The sitemap must therefore live in public/ so /sitemap.xml is served
        | as a real static XML file instead of being rewritten into Laravel.
        |--------------------------------------------------------------------------
        */

        $target = public_path('sitemap.xml');
        $temporary = $target.'.tmp';

        if (file_put_contents($temporary, $xml) === false) {
            $this->error('Unable to write temporary sitemap.');

            return self::FAILURE;
        }

        if (! rename($temporary, $target)) {
            @unlink($temporary);

            $this->error('Unable to publish sitemap.xml.');

            return self::FAILURE;
        }

        @chmod($target, 0644);

        /*
        |--------------------------------------------------------------------------
        | Maintain the domain-correct Sitemap line in robots.txt.
        |--------------------------------------------------------------------------
        */

        $robotsPath = public_path('robots.txt');

        $robots = is_file($robotsPath)
            ? (string) file_get_contents($robotsPath)
            : "User-agent: *\nAllow: /\n";

        $robots = preg_replace(
            '/^Sitemap:\s*.*$/mi',
            '',
            $robots
        ) ?? $robots;

        $robots = rtrim($robots)."\n\n";
        $robots .= 'Sitemap: '.$base."/sitemap.xml\n";

        file_put_contents(
            $robotsPath.'.tmp',
            $robots
        );

        rename(
            $robotsPath.'.tmp',
            $robotsPath
        );

        @chmod($robotsPath, 0644);

        $this->info(
            sprintf(
                'Sitemap generated: %d URLs (%d published properties).',
                count($entries),
                $properties->count()
            )
        );

        $this->line($target);

        return self::SUCCESS;
    }

    private function imageUrl(
        string $base,
        string $path
    ): ?string {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (
            filter_var(
                $path,
                FILTER_VALIDATE_URL
            )
        ) {
            return $path;
        }

        $path = ltrim($path, '/');

        /*
         * Already-public paths.
         */
        if (
            str_starts_with($path, 'storage/')
            || str_starts_with($path, 'public/')
            || str_starts_with($path, 'images/')
        ) {
            return $base.'/'.$path;
        }

        /*
         * Normal Laravel uploaded-file path.
         */
        $storageUrl = Storage::url($path);

        return $base.'/'.ltrim($storageUrl, '/');
    }

    private function xml(
        mixed $value
    ): string {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_XML1,
            'UTF-8'
        );
    }
}

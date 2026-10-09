<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\ContentBlock;
use App\Models\Property;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class AzariProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Example properties must never enter the live marketplace through a
        // direct seeder command, even when run outside DatabaseSeeder.
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('Fixture property seeder disabled outside local/testing.');
            return;
        }

        SiteSetting::put('site_name', 'Resarva', 'text', 'branding');
        SiteSetting::put('site_tagline', 'Private serviced residences', 'text', 'branding');
        SiteSetting::put('operating_regions', 'Nigeria and Rwanda', 'text', 'branding');

        $blocks = [
            ['home.hero.eyebrow', 'Hero eyebrow', 'Private serviced residences', 'text', 10],
            ['home.hero.title', 'Hero title', 'Exceptional stays, thoughtfully managed.', 'text', 20],
            ['home.hero.body', 'Hero description', 'Discover private, fully serviced residences across our operating destinations.', 'textarea', 30],
            ['home.about.title', 'About title', 'A considered collection of residences.', 'text', 40],
            ['home.about.body', 'About description', 'Every property is selected, prepared and managed to a consistent hospitality standard.', 'textarea', 50],
            ['home.featured.title', 'Featured properties title', 'Featured residences', 'text', 60],
            ['home.services.title', 'Services title', 'Hospitality beyond the front door.', 'text', 70],
        ];

        foreach ($blocks as [$key, $label, $value, $type, $sort]) {
            ContentBlock::query()->updateOrCreate(
                ['key' => $key],
                compact('label', 'value', 'type') + [
                    'page' => 'home',
                    'sort_order' => $sort,
                    'is_active' => true,
                ]
            );
        }

        $amenities = collect([
            ['Wi-Fi', 'wifi'],
            ['Air conditioning', 'ac_unit'],
            ['Backup power', 'bolt'],
            ['Secure parking', 'local_parking'],
            ['Housekeeping', 'cleaning_services'],
            ['Private kitchen', 'countertops'],
            ['Workspace', 'desk'],
            ['Pool', 'pool'],
        ])->mapWithKeys(function (array $amenity): array {
            $model = Amenity::query()->firstOrCreate(
                ['name' => $amenity[0]],
                ['icon' => $amenity[1]]
            );

            return [$amenity[0] => $model->id];
        });

        $properties = [
            ['Azure House', 'Victoria Island', 'Nigeria', 'Apartment', 3, 3, 6, 480000],
            ['Kigali Heights Residence', 'Kigali', 'Rwanda', 'Apartment', 2, 2, 4, 380000],
            ['The Ikoyi Residence', 'Ikoyi', 'Nigeria', 'Apartment', 4, 4, 8, 650000],
            ['The Admiralty Studio', 'Lekki', 'Nigeria', 'Studio', 1, 1, 2, 220000],
            ['Nyarutarama Suite', 'Kigali', 'Rwanda', 'Suite', 2, 2, 4, 410000],
            ['The Maitama Residence', 'Abuja', 'Nigeria', 'Apartment', 3, 3, 6, 520000],
        ];

        foreach ($properties as $index => [$name, $location, $country, $type, $beds, $baths, $guests, $rate]) {
            $property = Property::query()->firstOrCreate(
                ['name' => $name],
                [
                    'slug' => str($name)->slug().'-'.($index + 1),
                    'location' => $location,
                    'country' => $country,
                    'property_type' => $type,
                    'bedrooms' => $beds,
                    'bathrooms' => $baths,
                    'max_guests' => $guests,
                    'nightly_rate' => $rate,
                    'currency' => 'USD',
                    'short_description' => 'A fully serviced residence managed by Resarva.',
                    'description' => 'A private, fully serviced residence prepared for business and leisure stays.',
                    'is_featured' => true,
                    'is_published' => true,
                    'sort_order' => $index + 1,
                ]
            );

            $property->amenities()->syncWithoutDetaching(
                $amenities->take(5)->values()->all()
            );
        }
    }
}

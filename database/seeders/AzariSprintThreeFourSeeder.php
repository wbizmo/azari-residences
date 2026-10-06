<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\HomepageSection;
use App\Models\NavigationItem;
use App\Models\RoomType;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AzariSprintThreeFourSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@azariadmin.com'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make('12345678'),
                'staff_role' => 'administrator',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        foreach ([
            'site_name' => 'Resarva',
            'business_name' => 'Resarva Luxury Properties Ltd',
            'site_tagline' => 'Private serviced residences',
            'seo_title' => 'Resarva | Exceptional serviced stays',
            'seo_description' => 'Discover private, fully serviced Resarva stays designed around comfort, privacy and dependable hospitality.',
            'theme_primary' => '#12211b',
            'theme_secondary' => '#f3ecdd',
            'theme_accent' => '#bb8a3e',
            'theme_background' => '#f3ecdd',
            'theme_surface' => '#ffffff',
            'theme_text' => '#1c231f',
            'theme_muted' => '#6e7268',
        ] as $key => $value) {
            SiteSetting::put($key, $value, 'text', str_starts_with($key, 'theme_') ? 'theme' : 'general');
        }

        $navigation = [
            ['Residences', '#residences', 'header', 10],
            ['About', '#about', 'header', 20],
            ['Services', '#services', 'header', 30],
            ['Local guide', '#guide', 'header', 40],
            ['Contact', '#contact', 'header', 50],
        ];

        foreach ($navigation as [$label, $url, $location, $sort]) {
            NavigationItem::query()->updateOrCreate(
                compact('label', 'location'),
                [
                    'url' => $url,
                    'target' => '_self',
                    'sort_order' => $sort,
                    'is_active' => true,
                ]
            );
        }

        $sections = [
            ['hero', 'Hero', 'hero', 10],
            ['availability', 'Availability search', 'content', 20],
            ['about', 'Introduction', 'content', 30],
            ['residences', 'Featured residences', 'properties', 40],
            ['services', 'Guest services', 'services', 50],
        ];

        foreach ($sections as [$key, $name, $type, $sort]) {
            HomepageSection::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'type' => $type,
                    'status' => 'published',
                    'sort_order' => $sort,
                    'is_active' => true,
                ]
            );
        }

        foreach ([
            ['Apartment', 'apartment'],
            ['Studio', 'single_bed'],
            ['Suite', 'bedroom_parent'],
            ['Penthouse', 'roofing'],
            ['Private room', 'bed'],
        ] as [$name, $icon]) {
            RoomType::query()->updateOrCreate(
                ['name' => $name],
                ['icon' => $icon, 'is_active' => true]
            );
        }

        foreach ([
            ['Wi-Fi', 'wifi'],
            ['Air conditioning', 'ac_unit'],
            ['Backup power', 'bolt'],
            ['Secure parking', 'local_parking'],
            ['Housekeeping', 'cleaning_services'],
            ['Private kitchen', 'countertops'],
            ['Workspace', 'desk'],
            ['Swimming pool', 'pool'],
            ['Gym', 'fitness_center'],
            ['Smart lock', 'lock'],
        ] as $index => [$name, $icon]) {
            Amenity::query()->updateOrCreate(
                ['name' => $name],
                [
                    'icon' => $icon,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ]
            );
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        // Repair only recognizable legacy brand defaults. CMS operators may
        // intentionally configure a different legal entity or SEO campaign;
        // those custom values must not be overwritten.
        $defaults = [
            'site_name' => [
                'old' => ['Resarva', 'Reserva', 'Azari Residences'],
                'new' => 'Resavar',
            ],
            'business_name' => [
                'old' => ['Resarva Luxury Properties Ltd', 'Reserva Luxury Properties Ltd'],
                'new' => 'Resavar Luxury Properties Ltd',
            ],
            'seo_title' => [
                'old' => [
                    'Resarva | Exceptional serviced stays',
                    'Reserva | Exceptional serviced stays',
                ],
                'new' => 'Resavar | Exceptional serviced stays',
            ],
            'seo_description' => [
                'old' => [
                    'Discover private, fully serviced Resarva stays designed around comfort, privacy and dependable hospitality.',
                    'Discover private, fully serviced Reserva stays designed around comfort, privacy and dependable hospitality.',
                ],
                'new' => 'Discover private, fully serviced Resavar stays designed around comfort, privacy and dependable hospitality.',
            ],
        ];

        foreach ($defaults as $key => $map) {
            DB::table('site_settings')
                ->where('key', $key)
                ->whereIn('value', $map['old'])
                ->update(['value' => $map['new'], 'updated_at' => now()]);

            Cache::forget('site-setting:'.$key);
        }
    }

    public function down(): void
    {
        // Do not undo a brand correction or replace CMS-authored content.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            if (! Schema::hasColumn('properties', 'minimum_stay')) {
                $table->unsignedSmallInteger('minimum_stay')->default(1);
            }
            if (! Schema::hasColumn('properties', 'maximum_stay')) {
                $table->unsignedSmallInteger('maximum_stay')->nullable();
            }
            if (! Schema::hasColumn('properties', 'same_day_booking')) {
                $table->boolean('same_day_booking')->default(false);
            }
        });

        if (Schema::hasTable('properties') && Schema::hasTable('locations')) {
            DB::table('properties')
                ->whereNull('location_id')
                ->orderBy('id')
                ->get()
                ->each(function ($property): void {
                    $name = trim((string) ($property->location ?: 'Unassigned'));
                    $country = trim((string) ($property->country ?: 'Nigeria'));

                    $location = DB::table('locations')->where('name', $name)->first();
                    if (! $location) {
                        $id = DB::table('locations')->insertGetId([
                            'name' => $name,
                            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
                            'country' => $country,
                            'city' => $name,
                            'timezone' => 'Africa/Lagos',
                            'is_active' => true,
                            'sort_order' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $id = $location->id;
                    }

                    DB::table('properties')->where('id', $property->id)->update(['location_id' => $id]);
                });
        }

        if (Schema::hasTable('properties') && Schema::hasTable('room_types')) {
            DB::table('properties')
                ->whereNull('room_type_id')
                ->orderBy('id')
                ->get()
                ->each(function ($property): void {
                    $name = Str::headline((string) ($property->property_type ?: 'Residence'));
                    $slug = Str::slug($name);

                    $roomType = DB::table('room_types')->where('slug', $slug)->first();
                    if (! $roomType) {
                        $id = DB::table('room_types')->insertGetId([
                            'name' => $name,
                            'slug' => $slug.'-'.Str::lower(Str::random(4)),
                            'description' => null,
                            'icon' => 'bed',
                            'is_active' => true,
                            'sort_order' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $id = $roomType->id;
                    }

                    DB::table('properties')->where('id', $property->id)->update(['room_type_id' => $id]);
                });
        }
    }

    public function down(): void
    {
        // Data-linking migration intentionally keeps migrated inventory relationships.
    }
};

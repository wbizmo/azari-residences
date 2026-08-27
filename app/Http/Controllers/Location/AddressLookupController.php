<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AddressLookupController extends Controller
{
    private const PHOTON_BASE_URL = 'https://photon.komoot.io';

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:200'],
        ]);

        $query = trim($data['q']);
        $cacheKey = 'azari:photon:search:'.hash('sha256', mb_strtolower($query));

        try {
            $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($query): array {
                return Http::acceptJson()
                    ->withHeaders(['User-Agent' => 'AzariResidences/1.0'])
                    ->connectTimeout(4)
                    ->timeout(8)
                    ->get(self::PHOTON_BASE_URL.'/api/', [
                        'q' => $query,
                        'limit' => 5,
                        'lang' => 'en',
                    ])
                    ->throw()
                    ->json();
            });

            return response()->json([
                'features' => array_slice(is_array($payload['features'] ?? null) ? $payload['features'] : [], 0, 5),
            ]);
        } catch (Throwable) {
            return response()->json([
                'features' => [],
                'available' => false,
            ], 503);
        }
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $latitude = round((float) $data['lat'], 6);
        $longitude = round((float) $data['lon'], 6);
        $cacheKey = 'azari:photon:reverse:'.hash('sha256', $latitude.','.$longitude);

        try {
            $payload = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($latitude, $longitude): array {
                return Http::acceptJson()
                    ->withHeaders(['User-Agent' => 'AzariResidences/1.0'])
                    ->connectTimeout(4)
                    ->timeout(8)
                    ->get(self::PHOTON_BASE_URL.'/reverse', [
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'limit' => 1,
                        'lang' => 'en',
                    ])
                    ->throw()
                    ->json();
            });

            return response()->json([
                'features' => array_slice(is_array($payload['features'] ?? null) ? $payload['features'] : [], 0, 1),
            ]);
        } catch (Throwable) {
            return response()->json([
                'features' => [],
                'available' => false,
            ], 503);
        }
    }
}

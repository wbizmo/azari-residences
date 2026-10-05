<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\Search\DestinationSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DestinationSearchController extends Controller
{
    public function __invoke(Request $request, DestinationSearchService $search): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $query = $search->normalize($data['q']);
        $limit = (int) ($data['limit'] ?? config('reserva.search.autocomplete_default_limit', 8));

        $results = Cache::remember(
            'reserva:destination-suggest:'.sha1(mb_strtolower($query).'|'.$limit),
            now()->addSeconds((int) config('reserva.search.autocomplete_cache_seconds', 60)),
            fn () => $search->suggest($query, $limit)->all()
        );

        return response()->json([
            'query' => $query,
            'data' => $results,
        ])->header('Cache-Control', 'private, max-age='.(int) config('reserva.search.autocomplete_cache_seconds', 60));
    }
}

<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\Search\DestinationSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Live moderation/unpublication must remove an address/property suggestion
        // immediately. Do not serve a now-private listing from the old minute cache.
        $results = $search->suggest($query, $limit)->all();

        return response()->json([
            'query' => $query,
            'data' => $results,
        ])->header('Cache-Control', 'private, no-store');
    }
}

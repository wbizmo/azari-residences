<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Models\UserFavourite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserDiscoveryController extends Controller
{
    public function toggleFavourite(Request $request, Property $property): RedirectResponse|JsonResponse
    {
        abort_unless($property->is_published, 404);

        $favourite = UserFavourite::query()
            ->where('user_id', $request->user()->getKey())
            ->where('property_id', $property->getKey())
            ->first();

        if ($favourite) {
            $favourite->delete();
            $saved = false;
        } else {
            UserFavourite::query()->firstOrCreate([
                'user_id' => $request->user()->getKey(),
                'property_id' => $property->getKey(),
            ]);
            $saved = true;
        }

        if ($request->expectsJson()) {
            return response()->json(['saved' => $saved]);
        }

        return back()->with('success', $saved ? 'Stay saved to favourites.' : 'Stay removed from favourites.');
    }

    public function storeSearch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:12'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'destination' => ['nullable', 'string', 'max:120'],
            'destination_type' => ['nullable', 'string', 'max:30'],
            'destination_id' => ['nullable', 'integer', 'min:1'],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'room_type_id' => ['nullable', 'integer', 'min:1'],
            'property_type' => ['nullable', 'string', 'max:80'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'guest_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'amenities' => ['nullable', 'array', 'max:20'],
            'amenities.*' => ['integer', 'min:1'],
            'free_cancellation' => ['nullable', 'boolean'],
            'pay_later' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'max:30'],
        ]);

        $name = $data['name'] ?? null;
        unset($data['name']);

        $parameters = collect($data)
            ->reject(fn ($value) => $value === null || $value === '' || $value === [])
            ->sortKeys()
            ->all();

        $fingerprint = hash('sha256', json_encode($parameters));

        SavedSearch::query()->updateOrCreate(
            [
                'user_id' => $request->user()->getKey(),
                'fingerprint' => $fingerprint,
            ],
            [
                'name' => $name ? Str::limit(trim($name), 80) : null,
                'parameters' => $parameters,
                'last_used_at' => now(),
            ]
        );

        return back()->with('success', 'Search saved.');
    }

    public function destroySearch(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless((int) $savedSearch->user_id === (int) $request->user()->getKey(), 404);
        $savedSearch->delete();

        return back()->with('success', 'Saved search removed.');
    }
}
